<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /** A seller manages only its own products; everyone else (admins, store owners) the shared catalog. */
    private function isSeller($user): bool
    {
        return $user->hasRole('seller') && ! $user->hasRole('platform-admin');
    }

    /** Sellers' products are theirs alone to change; the shared catalog is changed by admins and store owners. */
    private function mayChange($user, Product $product): bool
    {
        if ($user->hasRole('platform-admin')) {
            return true;
        }

        return $this->isSeller($user) ? $product->seller_id === $user->company_id : $product->seller_id === null;
    }

    public function index(Request $request)
    {
        return Product::query()
            ->tap(fn ($q) => $this->visibleTo($request->user(), $q))
            ->when($request->filled('industry_id'), fn ($q) => $q->where('industry_id', $request->industry_id))
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$request->search}%")->orWhere('brand', 'like', "%{$request->search}%")
            ))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            // Only product managers may see inactive products.
            ->when(
                ! $this->isSeller($request->user()) && ! ($request->boolean('include_inactive') && $request->user()->can('products.update')),
                fn ($q) => $q->where('is_active', true)
            )
            ->withCount(['priceRules', 'fitments'])
            ->orderBy('name')
            ->get()
            // People who manage products also see how complete each one is.
            ->when($request->user()->can('products.update'), fn ($rows) => $rows->map(function ($p) {
                $r = app(\App\Services\ProductReadiness::class)->check($p);

                return $p->toArray() + ['readiness' => ['score' => $r['score'], 'ready' => $r['ready'], 'missing' => $r['total'] - $r['done']]];
            }));
    }

    /** The checklist for one product: what is done and what is still missing. */
    public function readiness(Request $request, Product $product)
    {
        abort_unless($request->user()->can('products.update') && $this->mayChange($request->user(), $product), 404);

        return app(\App\Services\ProductReadiness::class)->check($product);
    }

    /** The platform's category tree (groups with their categories), so forms offer a fixed list and nobody types a category. */
    public function categories(Request $request)
    {
        $rows = \App\Models\ProductCategory::where('is_active', true)->orderBy('position')->orderBy('name_en')->get()->groupBy(fn ($c) => $c->parent_id ?? 0);

        $build = function ($parent) use (&$build, $rows) {
            return ($rows[$parent] ?? collect())->values()->map(fn ($c) => ['key' => $c->key, 'name' => $c->name_en, 'name_ar' => $c->name_ar, 'children' => $build($c->id)])->all();
        };

        return $build(0);
    }

    /**
     * Sets how products come with installation (none / optional / required) for many at once. Only the products this person may change are
     * touched; the rest are reported back as skipped, never silently changed.
     */
    public function bulkInstallation(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer', 'installation' => 'required|in:none,optional,required']);
        $user = $request->user();
        $products = Product::query()->tap(fn ($q) => $this->visibleTo($user, $q))->whereIn('id', $data['ids'])->get();

        $updated = 0;
        foreach ($products as $product) {
            if ($this->mayChange($user, $product)) {
                $product->update(['installation' => $data['installation']]);
                $updated++;
            }
        }
        \App\Support\Audit::record('products.bulk_installation', null, ['installation' => $data['installation'], 'updated' => $updated], $user);

        return response()->json(['updated' => $updated, 'skipped' => count($data['ids']) - $updated]);
    }

    /** Sets the product-page layout for many products at once. Platform administrators only; every product it may not change is reported as skipped. */
    public function bulkPageTemplate(Request $request)
    {
        abort_unless($request->user()->hasRole('platform-admin'), 403);

        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer', 'page_template' => ['required', Rule::in(Product::PAGE_TEMPLATES)]]);
        $updated = Product::query()->tap(fn ($q) => $this->visibleTo($request->user(), $q))->whereIn('id', $data['ids'])->update(['page_template' => $data['page_template']]);
        \App\Support\Audit::record('products.bulk_page_template', null, ['page_template' => $data['page_template'], 'updated' => $updated], $request->user());

        return response()->json(['updated' => $updated, 'skipped' => count($data['ids']) - $updated]);
    }

    public function store(Request $request)
    {
        Product::$rawNames = true;        // the editing screens speak of the main (English) name, never the translated one
        $data = $this->validated($request);
        $user = $request->user();

        if ($this->isSeller($user)) {
            // A seller's products live in its own marketplace: the industry and country come from there, never from the form.
            $company = $user->company()->with('tenant')->first();
            $data = array_merge($data, [
                'seller_id' => $company->id,
                'industry_id' => $this->sellerIndustry($request, $company),
                'market' => app(\App\Support\Markets\Markets::class)->forCompany($company)?->code,
                'requires_shipping' => $request->boolean('requires_shipping', true),
            ]);
        }

        $product = Product::create($this->isSeller($user) ? app(\App\Services\ProductApproval::class)->onCreate($data) : $data);
        // A seller's product is buyable at its base price from the start (no hidden "price rule" step).
        app(\App\Services\DefaultPrice::class)->ensure($product);

        return response()->json($product, 201);
    }

    public function show(Request $request, Product $product)
    {
        Product::$rawNames = true;        // the editing screens speak of the main (English) name, never the translated one
        abort_unless($this->visibleTo($request->user(), Product::whereKey($product->id))->exists(), 404);

        // Whether the signed-in person may change it: the screen shows an editor only to those who can, instead of buttons that fail.
        return $product->load('priceRules')->toArray() + ['fitments' => app(\App\Services\Fitment\FitmentService::class)->describe($product), 'can_edit' => $request->user()->can('products.update') && $this->mayChange($request->user(), $product)];
    }

    public function update(Request $request, Product $product)
    {
        Product::$rawNames = true;        // the editing screens speak of the main (English) name, never the translated one
        abort_unless($this->mayChange($request->user(), $product), 404);
        $data = $this->validated($request, $product);

        if ($this->isSeller($request->user())) {
            unset($data['industry_id'], $data['market'], $data['seller_id']);
        }

        // Stock kept by ArmorHub changes only through the warehouse's movements, never by editing the product.
        if (($data['fulfilment'] ?? $product->fulfilment) === 'armorhub') {
            unset($data['stock_cartons']);
        }
        if (($data['fulfilment'] ?? null) === 'armorhub' && \App\Models\ProductBundleItem::where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['fulfilment' => ['This product is part of a bundle you ship yourself. Remove it from the bundle first.']]);
        }
        // Nothing a seller makes goes live until the catalogue team approves it: only an approved product can be switched on or off.
        if ($this->isSeller($request->user()) && ($data['is_active'] ?? false) && ! $product->is_active && $product->review_status !== 'approved' && app(\App\Services\ProductApproval::class)->required()) {
            throw ValidationException::withMessages(['is_active' => [\App\Support\PlatformBrand::in('A product goes live only after ArmorHub approves it. Submit it for review first.')]]);
        }
        // A seller publishes a draft only when shoppers have something to look at: at least one photo and a short description.
        if ($this->isSeller($request->user()) && ($data['is_active'] ?? false) && ! $product->is_active) {
            $photos = count(array_filter((array) ($data['images'] ?? $product->images)));
            $text = mb_strlen(trim((string) ($data['description'] ?? $product->description)));
            if ($photos < 1 || $text < 30) {
                throw ValidationException::withMessages(['is_active' => ['Add at least one photo and a description of 30 characters or more before you publish.']]);
            }
        }
        $oldPrice = (float) $product->base_price;
        $product->update($data);
        app(\App\Services\DefaultPrice::class)->follow($product, $oldPrice);
        if ($this->isSeller($request->user())) {
            app(\App\Services\ProductApproval::class)->reopened($product, array_keys($product->getChanges()));
        }
        app(\App\Services\PriceAlerts::class)->dropped($product, $oldPrice, (float) $product->base_price);

        // The same shape as show(), so a screen that replaces its copy with this one does not lose the edit permission.
        return $product->fresh()->toArray() + ['can_edit' => true];
    }

    /** The seller asks ArmorHub's catalogue team to look at the product. */
    public function submitReview(Request $request, Product $product, \App\Services\ProductApproval $approval)
    {
        abort_unless($this->mayChange($request->user(), $product), 404);

        return $approval->submit($product)->only(['id', 'review_status', 'review_note', 'is_active']);
    }

    /** Which cars the product fits. An empty list means every car (unless the product requires a fit, then it fits none). */
    public function syncFitments(Request $request, Product $product)
    {
        abort_unless($this->mayChange($request->user(), $product), 404);
        $data = $request->validate([
            'fitment_required' => 'sometimes|boolean',
            'rows' => 'present|array|max:200',
            'rows.*.vehicle_make_id' => 'nullable|integer|exists:vehicle_makes,id',
            'rows.*.vehicle_model_id' => 'nullable|integer|exists:vehicle_models,id',
            'rows.*.year_from' => 'nullable|integer|min:1980|max:2100',
            'rows.*.year_to' => 'nullable|integer|min:1980|max:2100|gte:rows.*.year_from',
            'rows.*.note' => 'nullable|string|max:160',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $data) {
            $product->fitments()->delete();
            foreach ($data['rows'] as $row) {
                // A model belongs to one make: if both are given they must agree.
                if (! empty($row['vehicle_model_id']) && ! empty($row['vehicle_make_id'])
                    && (int) \App\Modules\Automotive\Models\VehicleModel::whereKey($row['vehicle_model_id'])->value('vehicle_make_id') !== (int) $row['vehicle_make_id']) {
                    throw ValidationException::withMessages(['rows' => ['A model does not belong to that make.']]);
                }
                $product->fitments()->create(['vehicle_make_id' => $row['vehicle_make_id'] ?? null, 'vehicle_model_id' => $row['vehicle_model_id'] ?? null,
                    'year_from' => $row['year_from'] ?? null, 'year_to' => $row['year_to'] ?? null, 'note' => isset($row['note']) ? trim(strip_tags($row['note'])) : null]);
            }
            if (array_key_exists('fitment_required', $data)) {
                $product->update(['fitment_required' => $data['fitment_required']]);
            }
        });
        \App\Support\Audit::record('products.fitments_changed', $product, ['rows' => count($data['rows'])], $request->user());

        return ['fitment_required' => (bool) $product->fresh()->fitment_required, 'fitments' => app(\App\Services\Fitment\FitmentService::class)->describe($product)];
    }

    public function destroy(Request $request, Product $product)
    {
        abort_unless($this->mayChange($request->user(), $product), 404);

        // Orders are financial records; never delete them by deleting a product.
        if (\App\Models\Order::withoutGlobalScopes()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'This product has orders and cannot be deleted. Hide it from customers instead.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** A seller's product belongs to one industry of its market: the one it chose, or the market's only industry. */
    private function sellerIndustry(Request $request, $company): ?int
    {
        $allowed = $company->tenant?->industryIds() ?? [];

        // A market with one industry has nothing to choose; a country market with several asks the seller which one.
        if (count($allowed) <= 1) {
            return $allowed[0] ?? null;
        }

        $chosen = $request->integer('industry_id') ?: null;
        if ($chosen === null) {
            throw ValidationException::withMessages(['industry_id' => ['Choose the industry of this product.']]);
        }
        if (! in_array($chosen, $allowed, true)) {
            throw ValidationException::withMessages(['industry_id' => ['This market does not run that industry.']]);
        }

        return $chosen;
    }

    /**
     * Platform admins see everything. Everyone else sees only products of their own industry
     * (or industry-less ones); hiding a product also hides it from non-managers.
     */
    private function visibleTo($user, $query)
    {
        if ($user->hasRole('platform-admin')) {
            return $query;
        }

        // A seller sees its own products and nothing else.
        if ($this->isSeller($user)) {
            return $query->where('seller_id', $user->company_id);
        }

        // Everyone else sees the shared catalog and the products of the active sellers in their marketplace.
        $query->visibleInTenant($user->company?->tenant_id);

        $industries = $user->industryIds();
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;

        $query->where(fn ($q) => $q->whereNull('industry_id')->when($industries, fn ($w) => $w->orWhereIn('industry_id', $industries)));
        // A product limited to one country is only shown in that country.
        $query->where(fn ($q) => $q->whereNull('market')->when($market, fn ($w) => $w->orWhere('market', $market)));

        if (! $user->can('products.update')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    /** A lowercase address that no other product uses: the wanted one, or the same with a short suffix. */
    private function freeSlug(string $wanted): string
    {
        $base = \Illuminate\Support\Str::slug($wanted) ?: 'product';
        $base = mb_substr($base, 0, 200);
        $slug = $base;
        while (Product::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.strtolower(\Illuminate\Support\Str::random(4));
        }

        return $slug;
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $sometimes = $product ? 'sometimes|' : '';

        // One spelling per category ("Skin Care", "skin care" and "skin-care" are the same one), and never empty.
        if ($request->has('category')) {
            $request->merge(['category' => \Illuminate\Support\Str::slug((string) $request->input('category'))]);
        }

        // Numbers typed with Arabic-Indic digits (and the Arabic decimal mark) are read as the numbers they are.
        foreach (['base_price', 'compare_at_price', 'stock_cartons', 'units_per_carton', 'warranty_years', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm', 'carton_weight_kg'] as $numeric) {
            if (is_string($request->input($numeric))) {
                $request->merge([$numeric => strtr(trim($request->input($numeric)), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '،' => '.', ',' => '.'])]);
            }
        }

        // The web address of a product is made for the seller, from the name in any language, and is never "already taken".
        if (! $product) {
            $request->merge(['slug' => $this->freeSlug((string) ($request->input('slug') ?: $request->input('name')))]);
        }

        if ($request->filled('sku')) {
            $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        }

        $rules = [
            'industry_id' => 'nullable|exists:industries,id',
            'market' => ['nullable', 'string', Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
            'category' => [...($product ? ['sometimes'] : []), 'required', 'string', 'max:80', Rule::exists('product_categories', 'key')->where('is_active', true)],
            'sku' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'units_per_carton' => 'nullable|integer|min:1|max:100000',
            'carton_length_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_width_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_height_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_weight_kg' => 'nullable|numeric|min:0.001|max:2000',
            'stock_cartons' => 'nullable|integer|min:0|max:10000000',
            'fulfilment' => 'sometimes|in:seller,armorhub',
            'brand' => 'nullable|string|max:255',
            'name' => $sometimes.'required|string|max:255',
            'slug' => [
                ...($product ? ['sometimes'] : []),
                'required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
            'description' => 'nullable|string|max:10000',
            // The same listing in Arabic: shoppers browsing in Arabic read these instead (the other copy is the fallback).
            'name_ar' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string|max:10000',
            // decimal(12,2): a larger number would be a database error, not a validation message.
            'base_price' => $sometimes.'required|numeric|min:0|max:9999999999',
            'warranty_years' => 'nullable|integer|min:0|max:50',
            // Only the keys the screens use are kept (see below): anything else sent is dropped, never stored.
            'attributes' => 'nullable|array',
            // For cars and motorcycles: a plain sentence the seller writes about what it fits, shown in bold (never a required section).
            'attributes.fits_note' => 'nullable|string|max:300',
            'attributes.highlights_ar' => 'nullable|array|max:8',
            'attributes.highlights_ar.*' => 'string|max:120',
            'attributes.highlights' => 'nullable|array|max:8',
            'attributes.highlights.*' => 'string|max:120',
            // The content each page layout asks for (what the form shows once a layout is chosen).
            'attributes.page' => 'nullable|array',
            'attributes.page.headline' => 'nullable|string|max:160',
            'attributes.page.steps' => 'nullable|array|max:4',
            'attributes.page.steps.*' => 'array',
            'attributes.page.steps.*.title' => 'required|string|max:80',
            'attributes.page.faqs' => 'nullable|array|max:8',
            'attributes.page.faqs.*' => 'array',
            'attributes.page.faqs.*.q' => 'required|string|max:160',
            'attributes.page.faqs.*.a' => 'required|string|max:600',
            'attributes.page.specs' => 'nullable|array|max:10',
            'attributes.page.specs.*' => 'array',
            'attributes.page.specs.*.label' => 'required|string|max:60',
            'attributes.page.specs.*.value' => 'required|string|max:120',
            'attributes.page.tiers' => 'nullable|array|max:3',
            'attributes.page.tiers.*' => 'array',
            'attributes.page.tiers.*.name' => 'required|string|max:40',
            'attributes.page.tiers.*.text' => 'nullable|string|max:160',
            'attributes.page.addon_ids' => 'nullable|array|max:4',
            'attributes.page.addon_ids.*' => ['integer', 'distinct', fn ($a, $v, $fail) => $this->visibleTo($request->user(), Product::whereKey($v))->exists() || $fail('That product was not found.')],
            'attributes.page.free_delivery_from' => 'nullable|numeric|min:0|max:9999999999',
            'attributes.page.warranty_terms' => 'nullable|string|max:600',
            'attributes.page.lead_time' => 'nullable|string|max:80',
            'attributes.page.quote_note' => 'nullable|string|max:300',
            'images' => 'nullable|array|max:8',
            'images.*' => ['string', 'max:500', fn ($attr, $value, $fail) => \App\Support\ImageRef::valid($value) || $fail('Each image must be an https address or an uploaded image.')],
            'compare_at_price' => 'nullable|numeric|min:0|max:9999999999',
            'is_active' => 'boolean',
            'fitment_required' => 'sometimes|boolean',
            // fixed: bought at its price; quote: bought only against a seller's quote (the customer asks first).
            'sale_mode' => 'sometimes|in:fixed,quote',
            'requires_shipping' => 'boolean',
            // Whether the buyer may need a centre to fit it: none, optional or required. The product is always sold on its own.
            'installation' => 'sometimes|in:none,optional,required',
            // The page layout is an administrator's choice.
            'page_template' => ['sometimes', 'nullable', Rule::in(Product::PAGE_TEMPLATES)],
        ];

        // A seller cannot choose the industry or country: they come from its marketplace (set in store()).
        if ($this->isSeller($request->user())) {
            unset($rules['market']);
            $rules['industry_id'] = 'nullable|integer';
        }

        // Administrators and sellers (for their own products) choose the page layout; for everyone else the field is ignored.
        if (! $request->user()->hasRole('platform-admin') && ! $this->isSeller($request->user())) {
            unset($rules['page_template']);
        }

        $data = $request->validate($rules);
        if (isset($data['attributes'])) {
            $data['attributes'] = $this->plainText($this->knownKeys($data['attributes']));
        }
        $this->checkFulfilment($data, $request, $product);
        $this->checkListing($data, $request, $product);

        return $data;
    }

    /**
     * Shoppers read the English copy when the platform is in English and the Arabic copy when it is in Arabic, never a mix: a seller gives both,
     * each in its own language (the setting products.require_both_languages can relax the "both" part, never the "own language" part).
     */
    private function checkLanguages(array $data, Request $request, ?Product $product): void
    {
        $has = fn (string $re, ?string $text) => $text === null || $text === '' || (bool) preg_match($re, $text);
        $mix = fn (string $field, string $why) => throw ValidationException::withMessages([$field => [$why]]);
        foreach (['name' => $data['name'] ?? null, 'description' => $data['description'] ?? null] as $f => $v) {
            if (! $has('/[A-Za-z]/', $v)) {
                $mix($f, 'Write this in English: it is the copy shoppers read when the platform is in English.');
            }
        }
        foreach (['name_ar' => $data['name_ar'] ?? null, 'description_ar' => $data['description_ar'] ?? null] as $f => $v) {
            if (! $has('/\p{Arabic}/u', $v)) {
                $mix($f, 'Write this in Arabic: it is the copy shoppers read when the platform is in Arabic.');
            }
        }
        if (! app(\App\Support\Settings\Settings::class)->bool('products.require_both_languages')) {
            return;
        }
        // Creating, or publishing a draft: every one of the four must be there.
        $publishing = $product === null || (($data['is_active'] ?? false) && ! $product->is_active);
        if ($publishing) {
            $val = fn (string $k) => trim((string) (array_key_exists($k, $data) ? $data[$k] : ($product?->{$k} ?? '')));
            foreach (['name' => 'English name', 'name_ar' => 'Arabic name', 'description' => 'English description', 'description_ar' => 'Arabic description'] as $k => $label) {
                if ($val($k) === '') {
                    $mix($k, "Add the {$label}: every product needs its name and description in English and in Arabic.");
                }
            }
        }
    }

    /** What a shopper reads before paying must not carry a way around the platform, and the prices must make sense. */
    private function checkListing(array $data, Request $request, ?Product $product): void
    {
        if (! $this->isSeller($request->user())) {
            return;
        }
        $this->checkLanguages($data, $request, $product);
        $texts = ['description' => $data['description'] ?? null, 'description_ar' => $data['description_ar'] ?? null];     // names and brands carry model numbers and years, so only free text is checked
        foreach (($data['attributes']['highlights'] ?? []) as $i => $line) {
            $texts["attributes.highlights.{$i}"] = $line;
        }
        foreach (($data['attributes']['highlights_ar'] ?? []) as $i => $line) {
            $texts["attributes.highlights_ar.{$i}"] = $line;
            if (! preg_match('/\p{Arabic}/u', (string) $line)) {
                throw ValidationException::withMessages(["attributes.highlights_ar.{$i}" => ['Write this in Arabic: it is the copy shoppers read when the platform is in Arabic.']]);
            }
        }
        foreach ($texts as $field => $text) {
            if (is_string($text) && \App\Support\ContactGuard::contains($text)) {
                throw ValidationException::withMessages([$field => ['Please do not put phone numbers, e-mails or links here. Shoppers reach you through the platform.']]);
            }
        }
        $price = array_key_exists('base_price', $data) ? (float) $data['base_price'] : (float) ($product?->base_price ?? 0);
        if (array_key_exists('base_price', $data) && $price < 0.01) {
            throw ValidationException::withMessages(['base_price' => ['The price must be more than zero.']]);
        }
        $was = array_key_exists('compare_at_price', $data) ? $data['compare_at_price'] : $product?->compare_at_price;
        if ($was !== null && (float) $was > 0 && (float) $was <= $price) {
            throw ValidationException::withMessages(['compare_at_price' => ['The price before the discount must be higher than the price.']]);
        }
    }

    /** Keeps only what the screens use: the highlights and the page content, each with its known fields and shapes. */
    private function knownKeys(array $attributes): array
    {
        $page = \Illuminate\Support\Arr::only($attributes['page'] ?? [], ['headline', 'steps', 'faqs', 'specs', 'tiers', 'addon_ids', 'free_delivery_from', 'warranty_terms', 'lead_time', 'quote_note']);
        foreach (['steps' => ['title'], 'faqs' => ['q', 'a'], 'specs' => ['label', 'value'], 'tiers' => ['name', 'text']] as $list => $keys) {
            if (isset($page[$list])) {
                $page[$list] = array_values(array_map(fn ($row) => \Illuminate\Support\Arr::only($row, $keys), $page[$list]));
            }
        }
        if (isset($page['addon_ids'])) {
            $page['addon_ids'] = array_values(array_map('intval', $page['addon_ids']));
        }

        $out = array_filter(['highlights' => isset($attributes['highlights']) ? array_values($attributes['highlights']) : null, 'highlights_ar' => isset($attributes['highlights_ar']) ? array_values($attributes['highlights_ar']) : null, 'fits_note' => ($attributes['fits_note'] ?? '') !== '' ? $attributes['fits_note'] : null, 'page' => $page ?: null], fn ($v) => $v !== null);

        return $out;
    }

    /** Product copy is plain text: markup is removed on the way in, so no other screen, email or export ever has to guess whether it is safe. */
    private function plainText(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->plainText($v), $value);
        }

        return is_string($value) ? trim(strip_tags($value)) : $value;
    }

    /** ArmorHub can only store and ship what it can handle: a physical product with complete carton data, in a market that has a warehouse. */
    private function checkFulfilment(array $data, Request $request, ?Product $product): void
    {
        $wants = $data['fulfilment'] ?? $product?->fulfilment ?? 'seller';
        $stock = app(\App\Services\Warehouse\WarehouseService::class);

        if ($wants === 'seller') {
            if ($product && ($data['fulfilment'] ?? null) === 'seller' && $product->fulfilment === 'armorhub' && $stock->unitsOf($product) > 0) {
                throw ValidationException::withMessages(['fulfilment' => ['Units are still in the warehouse. Ask the warehouse to return them first.']]);
            }

            return;
        }

        $get = fn ($k) => array_key_exists($k, $data) ? $data[$k] : $product?->{$k};
        if (! ($get('requires_shipping') ?? $request->boolean('requires_shipping', true))) {
            throw ValidationException::withMessages(['fulfilment' => [\App\Support\PlatformBrand::in('Only physical products can be stored by ArmorHub.')]]);
        }
        foreach (['units_per_carton', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm', 'carton_weight_kg'] as $k) {
            if (! $get($k)) {
                throw ValidationException::withMessages(['fulfilment' => ['Fill in the carton size, weight and units per carton first: the warehouse needs them.']]);
            }
        }
        $company = $product?->seller_id ? \App\Models\Company::withoutGlobalScopes()->find($product->seller_id) : $request->user()->company;
        if (! $company?->tenant_id || ! $stock->warehouseFor($company->tenant_id)) {
            throw ValidationException::withMessages(['fulfilment' => ['This market has no open warehouse yet.']]);
        }
    }
}
