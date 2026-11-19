<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use \App\Models\Concerns\Auditable;
    public function priceRules(): HasMany
{
    return $this->hasMany(
        ProductPriceRule::class
    );
}


    /** The product-page layouts an administrator can choose from. The first is the default. */
    public const PAGE_TEMPLATES = ['classic', 'tiers', 'builder', 'story', 'compare', 'marketplace', 'bundle', 'warranty', 'quote', 'immersive'];

    protected $fillable = [
        'industry_id',
        'seller_id',
        'market',
        'category',
        'brand',
        'name',
        'name_ar',
        'review_status',
        'slug',
        'description',
        'description_ar',
        'images',
        'compare_at_price',
        'base_price',
        'warranty_years',
        'attributes',
        'is_active',
        'requires_shipping',
        'installation',
        'page_template',
        'sku',
        'units_per_carton',
        'carton_length_cm',
        'carton_width_cm',
        'carton_height_cm',
        'carton_weight_kg',
        'stock_cartons',
        'fulfilment',
        'fitment_required',
        'sale_mode',
    ];


    /** Set by the screens where the seller edits the English copy itself: they must see the main name, whatever the language. */
    public static bool $rawNames = false;

    /** Everywhere else a product is named in the language being browsed (its Arabic name when the platform is in Arabic and there is one). */
    public function getNameAttribute($value): ?string
    {
        $ar = $this->attributes['name_ar'] ?? null;

        return ! self::$rawNames && $ar && app()->getLocale() === 'ar' ? $ar : $value;
    }

    /** The products a bundle is made of (empty for an ordinary product). */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_id');
    }

    /** Customers' reviews of it. */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /** Which cars it fits (none = fits everything, unless fitment is required). */
    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class);
    }

    /** The company that sells this product, or null for the shared catalog. */
    public function seller()
    {
        return $this->belongsTo(Company::class, 'seller_id')->withoutGlobalScopes();
    }

    /** What a tenant's stores may show: the shared catalog plus products of its active sellers. */
    public function scopeVisibleInTenant($query, ?int $tenantId)
    {
        return $query->where(fn ($q) => $q->whereNull('seller_id')->when($tenantId, fn ($w) => $w->orWhereIn('seller_id',
            Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'supplier')->where('status', 'active')->select('id'))));
    }

    protected $casts = [
        'base_price'=>'decimal:2',
        'submitted_at'=>'datetime',
        'reviewed_at'=>'datetime',
        'is_active'=>'boolean',
        'requires_shipping'=>'boolean',
        'attributes'=>'array',
        'images'=>'array',
        'compare_at_price'=>'decimal:2',
        'carton_length_cm'=>'decimal:2',
        'carton_width_cm'=>'decimal:2',
        'carton_height_cm'=>'decimal:2',
        'carton_weight_kg'=>'decimal:3',
        'fitment_required'=>'boolean',
    ];

    /** Every product has a SKU: the seller's own code, or one made from the country and the product number. */
    protected static function booted(): void
    {
        static::created(function (Product $p) {
            if (! $p->sku) {
                $p->forceFill(['sku' => sprintf('AH-%s-%06d', $p->market ?: 'GL', $p->id)])->saveQuietly();
            }
        });
    }

    /** Units in stock = whole cartons x units per carton. */
    public function stockUnits(): int
    {
        return (int) $this->stock_cartons * (int) ($this->units_per_carton ?: 1);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
