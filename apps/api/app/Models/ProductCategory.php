<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $fillable = ['parent_id', 'key', 'name_en', 'name_ar', 'position', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function name(string $locale = 'en'): string
    {
        return $locale === 'ar' && $this->name_ar ? $this->name_ar : $this->name_en;
    }
}
