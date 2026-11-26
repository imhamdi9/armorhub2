<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\DefaultPrice;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A seller's bundles: several of their own products sold together at one price. A bundle is an ordinary product to the shopper (one price, one
 * parcel, one return), so the cart, delivery, refunds and earnings work as they do for everything else. Bundles are made of goods the seller ships
 * itself: stock that ArmorHub's warehouse holds is counted per product, so it cannot be split inside a bundle.
 */
class SellerBundleController extends Controller
{
    private function sellerId(Request $request): int
    {
        abort_unless($request->user()->hasRole('seller') && $request->user()->company_id, 403);

        return (int) $request->user()->company_id;
    }

    public function index(Request $request)
    {
        $cid = $this->sellerId($request);

        return Product::where('seller_id', $cid)->whereHas('bundleItems')->with('bundleItems.product:id,name,name_ar,base_price,images')->latest('id')->get()->map(fn ($b) => $this->row($b))->values();
    }

    public function store(Request $request)
    {
        $cid = $this->sellerId($request);
        $data = $this->validated($request);
        $parts = $this->parts($cid, $data['items']);
        $first = $parts->first()['product'];
        $this->mustSave((float) $data['base_price'], $this->worth($parts));

        $bundle = DB::transaction(function () use ($data, $parts, $cid, $first) {
            $bundle = Product::create([
                'seller_id' => $cid, 'industry_id' => $first->industry_id, 'market' => $first->market, 'category' => $first->category, 'name' => $data['name'], 'name_ar' => $data['name_ar'] ?? null,
                'description' => $data['description'] ?? null, 'description_ar' => $data['description_ar'] ?? null, 'base_price' => $data['base_price'], 'compare_at_price' => $this->worth($parts),
                'slug' => $this->freeSlug($data['name']), 'requires_shipping' => true, 'images' => $parts->flatMap(fn ($p) => array_slice((array) $p['product']->images, 0, 1))->take(4)->values()->all(),
            ]);
            foreach ($parts as $p) {
                $bundle->bundleItems()->create(['product_id' => $p['product']->id, 'quantity' => $p['quantity']]);
            }
            app(DefaultPrice::class)->ensure($bundle);
            // A bundle is a product like any other: it is shown only once the catalogue team has approved it.
            $approval = app(\App\Services\ProductApproval::class);
            $bundle->forceFill($approval->onCreate(['is_active' => true]))->save();
            $approval->submit($bundle->refresh());

            return $bundle;
        });

        return response()->json($this->row($bundle->load('bundleItems.product:id,name,name_ar,base_price,images')), 201);
    }

    public function update(Request $request, int $id)
    {
        $cid = $this->sellerId($request);
        $bundle = Product::where('seller_id', $cid)->whereHas('bundleItems')->findOrFail($id);
        $data = $this->validated($request, true);

        DB::transaction(function () use ($bundle, $data, $cid, $request) {
            $old = (float) $bundle->base_price;
            $this->mustSave((float) ($data['base_price'] ?? $bundle->base_price), isset($data['items']) ? $this->worth($this->parts($cid, $data['items'])) : (float) $bundle->compare_at_price);
            $fill = array_intersect_key($data, array_flip(['name', 'name_ar', 'description', 'description_ar', 'base_price', 'is_active']));
            if (isset($data['items'])) {
                $parts = $this->parts($cid, $data['items']);
                $bundle->bundleItems()->delete();
                foreach ($parts as $p) {
                    $bundle->bundleItems()->create(['product_id' => $p['product']->id, 'quantity' => $p['quantity']]);
                }
                $fill['compare_at_price'] = $this->worth($parts);
            }
            if (($fill['is_active'] ?? false) && $bundle->review_status !== 'approved' && app(\App\Services\ProductApproval::class)->required()) {
                throw ValidationException::withMessages(['is_active' => [\App\Support\PlatformBrand::in('A bundle goes live only after ArmorHub approves it.')]]);
            }
            $bundle->update($fill);
            app(DefaultPrice::class)->follow($bundle, $old);
            app(\App\Services\ProductApproval::class)->reopened($bundle, array_keys($bundle->getChanges()));
        });

        return $this->row($bundle->fresh()->load('bundleItems.product:id,name,name_ar,base_price,images'));
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $s = $partial ? 'sometimes|' : '';
        $data = $request->validate([
            'name' => $s.'required|string|min:3|max:255', 'name_ar' => $s.'required|string|min:3|max:255',
            'description' => $s.'required|string|min:30|max:10000', 'description_ar' => $s.'required|string|min:30|max:10000',
            'base_price' => $s.'required|numeric|gt:0|max:9999999999',
            'items' => $s.'required|array|min:2|max:12', 'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|min:1|max:50',
            'is_active' => 'sometimes|boolean',
        ]);
        // Each copy in its own language: English for the English platform, Arabic for the Arabic one.
        foreach (['name' => '/[A-Za-z]/', 'description' => '/[A-Za-z]/', 'name_ar' => '/\p{Arabic}/u', 'description_ar' => '/\p{Arabic}/u'] as $f => $re) {
            if (! empty($data[$f]) && ! preg_match($re, $data[$f])) {
                throw ValidationException::withMessages([$f => [str_ends_with($f, '_ar') ? 'Write this in Arabic: it is the copy shoppers read when the platform is in Arabic.' : 'Write this in English: it is the copy shoppers read when the platform is in English.']]);
            }
        }
        foreach (['description', 'description_ar'] as $f) {
            if (! empty($data[$f]) && ContactGuard::contains($data[$f])) {
                throw ValidationException::withMessages([$f => ['Please do not put phone numbers, e-mails or links here. Shoppers reach you through the platform.']]);
            }
        }

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, array{product: Product, quantity: int}> */
    private function parts(int $sellerId, array $items)
    {
        $products = Product::where('seller_id', $sellerId)->whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');
        $parts = collect();
        foreach ($items as $i => $row) {
            $p = $products[$row['product_id']] ?? null;
            if (! $p) {
                throw ValidationException::withMessages(["items.$i.product_id" => ['You can only bundle your own products.']]);
            }
            if ($p->fulfilment === 'armorhub') {
                throw ValidationException::withMessages(["items.$i.product_id" => [\App\Support\PlatformBrand::in('This product is stored in ArmorHub\'s warehouse, so it cannot be part of a bundle. Bundle products you ship yourself.')]]);
            }
            if ($p->bundleItems()->exists()) {
                throw ValidationException::withMessages(["items.$i.product_id" => ['A bundle cannot contain another bundle.']]);
            }
            $parts->push(['product' => $p, 'quantity' => (int) $row['quantity']]);
        }

        return $parts;
    }

    /** A bundle is a saving: its price must be lower than the parts cost one by one. */
    private function mustSave(float $price, float $worth): void
    {
        if ($price >= $worth) {
            throw ValidationException::withMessages(['base_price' => [__('The bundle price must be lower than the products cost separately (:n), or it is not a saving.', ['n' => number_format($worth, 2)])]]);
        }
    }

    /** What the parts cost separately: shown as the "was" price, so the saving is clear. */
    private function worth($parts): float
    {
        return round($parts->sum(fn ($p) => (float) $p['product']->base_price * $p['quantity']), 2);
    }

    private function freeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bundle';
        $slug = $base;
        while (Product::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.strtolower(Str::random(4));
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function row(Product $b): array
    {
        $items = $b->bundleItems->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $i->product?->name, 'quantity' => (int) $i->quantity, 'price' => (float) ($i->product?->base_price ?? 0)])->values();

        return ['id' => $b->id, 'name' => $b->name, 'name_ar' => $b->name_ar, 'description' => $b->description, 'base_price' => (float) $b->base_price, 'worth' => $items->sum(fn ($i) => $i['price'] * $i['quantity']),
            'is_active' => (bool) $b->is_active, 'review_status' => $b->review_status, 'review_note' => $b->review_note, 'items' => $items];
    }
}
