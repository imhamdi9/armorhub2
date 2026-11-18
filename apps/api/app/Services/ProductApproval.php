<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Notifications\SimpleNotice;
use App\Support\Audit;
use App\Support\Settings\Settings;
use Illuminate\Validation\ValidationException;

/**
 * A seller's product is never shown to shoppers until ArmorHub's catalogue team approves it (setting products.require_approval).
 * draft -> (seller submits) -> pending -> approved | rejected (with a reason; the seller fixes it and submits again).
 * Changing what shoppers read or see on an approved product (name, description, photos, category) sends it back for review.
 */
class ProductApproval
{
    /** Fields whose change needs a new look. */
    public const REVIEWED_FIELDS = ['name', 'name_ar', 'description', 'description_ar', 'images', 'category'];

    public function __construct(private Settings $settings)
    {
    }

    public function required(): bool
    {
        return $this->settings->bool('products.require_approval');
    }

    /** A new seller product starts as a draft, hidden, whatever the form said. */
    public function onCreate(array $data): array
    {
        if (! $this->required()) {
            return $data;
        }

        return ['is_active' => false, 'review_status' => 'draft'] + $data;
    }

    /** The seller asks the team to look at it. */
    public function submit(Product $product): Product
    {
        if (! $this->required() || $product->review_status === 'approved') {
            return $product;
        }
        $photos = count(array_filter((array) $product->images));
        $text = mb_strlen(trim((string) $product->description));
        if ($photos < 1 || $text < 30) {
            throw ValidationException::withMessages(['review' => ['Add at least one photo and a description of 30 characters or more before you submit it for review.']]);
        }
        $product->forceFill(['review_status' => 'pending', 'is_active' => false, 'review_note' => null, 'submitted_at' => now()])->save();
        $this->tellReviewers($product);

        return $product;
    }

    /** An approved product was edited: hidden until looked at again. */
    public function reopened(Product $product, array $changed): void
    {
        if (! $this->required() || $product->review_status !== 'approved' || ! array_intersect(self::REVIEWED_FIELDS, $changed)) {
            return;
        }
        $product->forceFill(['review_status' => 'pending', 'is_active' => false, 'submitted_at' => now(), 'review_note' => null])->save();
        $this->tellReviewers($product);
    }

    public function approve(Product $product, User $by): Product
    {
        $this->mustBePending($product);
        $product->forceFill(['review_status' => 'approved', 'is_active' => true, 'review_note' => null, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        Audit::record('products.approved', $product, [], $by);
        $this->tellSeller($product, 'Your product was approved', ':name is now live in the shop.');

        return $product;
    }

    public function reject(Product $product, User $by, string $why): Product
    {
        $this->mustBePending($product);
        $product->forceFill(['review_status' => 'rejected', 'is_active' => false, 'review_note' => $why, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        Audit::record('products.rejected', $product, ['why' => $why], $by);
        $this->tellSeller($product, 'Your product needs changes', ':name: :why', ['why' => $why]);

        return $product;
    }

    private function mustBePending(Product $product): void
    {
        if ($product->review_status !== 'pending') {
            throw ValidationException::withMessages(['product' => ['This product is not waiting for review.']]);
        }
    }

    private function tellSeller(Product $p, string $title, string $body, array $extra = []): void
    {
        User::withoutGlobalScopes()->where('company_id', $p->seller_id)->role('seller')->get()->each(fn ($u) => $u->notify(new SimpleNotice($title, $body, "/dashboard/products/{$p->id}", ['name' => (string) $p->name] + $extra)));
    }

    /** The reviewers of the seller's market, and the platform administrators. */
    private function tellReviewers(Product $p): void
    {
        $tenant = \App\Models\Company::withoutGlobalScopes()->whereKey($p->seller_id)->value('tenant_id');
        $reviewers = User::withoutGlobalScopes()->whereHas('roles', fn ($r) => $r->whereIn('name', ['catalog-reviewer', 'platform-admin']))->get()
            ->filter(fn ($u) => $u->hasRole('platform-admin') || ($tenant && (int) \App\Models\Company::withoutGlobalScopes()->whereKey($u->company_id)->value('tenant_id') === (int) $tenant));
        $reviewers->each(fn ($u) => $u->notify(new SimpleNotice('A product waits for review', ':name (:seller)', '/dashboard/admin/product-reviews', ['name' => (string) $p->name, 'seller' => (string) \App\Models\Company::withoutGlobalScopes()->whereKey($p->seller_id)->value('name')])));
    }
}
