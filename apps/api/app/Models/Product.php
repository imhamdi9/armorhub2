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
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
