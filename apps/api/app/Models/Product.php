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


    protected $fillable = [
        'industry_id',
        'seller_id',
        'market',
        'category',
        'brand',
        'name',
        'slug',
        'description',
        'images',
        'compare_at_price',
        'base_price',
        'warranty_years',
        'attributes',
        'is_active',
        'requires_shipping',
        'installation',
        'sku',
        'units_per_carton',
        'carton_length_cm',
        'carton_width_cm',
        'carton_height_cm',
        'carton_weight_kg',
        'stock_cartons',
    ];


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
        'is_active'=>'boolean',
        'requires_shipping'=>'boolean',
        'attributes'=>'array',
        'images'=>'array',
        'compare_at_price'=>'decimal:2',
        'carton_length_cm'=>'decimal:2',
        'carton_width_cm'=>'decimal:2',
        'carton_height_cm'=>'decimal:2',
        'carton_weight_kg'=>'decimal:3',
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
