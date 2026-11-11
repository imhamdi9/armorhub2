<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Models\Concerns\BelongsToCompany;

class Company extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;
    use BelongsToCompany;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'slug',
        'legal_name',
        'email',
        'phone',
        'logo',
        'type',
        'status',
        'settings',
    ];

    public function users()
    {
    return $this->hasMany(User::class);
    }


    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {

      static::creating(function ($company) {

        if (!$company->uuid) {
            $company->uuid = (string) Str::uuid();
        }

        // Every company gets a unique, URL-safe address for its storefront.
        $company->slug ??= static::uniqueSlug($company->name);

     });
    }


    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        for ($i = 2; static::withoutGlobalScopes()->withTrashed()->where('slug', $slug)->exists() || in_array($slug, self::RESERVED_SLUGS, true); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** Words that would clash with the site's own addresses. */
    public const RESERVED_SLUGS = ['api', 'admin', 'login', 'register', 'dashboard', 'forgot-password', 'reset-password', 'www', 'static', 'assets'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }


    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}