<?php

namespace App\Support;

use App\Models\ProductCategory;
use Illuminate\Support\Str;

/** The platform's own product taxonomy: a two-level tree every seller files products under. Admins can extend it. */
class CategoryTaxonomy
{
    /** @var array<string, array{0: string, 1: string, 2: array<string, array{0: string, 1: string}>}> group key => [en, ar, children key => [en, ar]] */
    public const DEFAULT = [
        'automotive' => ['Automotive', 'السيارات', [
            'car-care' => ['Car care', 'العناية بالسيارة'], 'accessories' => ['Accessories', 'إكسسوارات'], 'spare-parts' => ['Spare parts', 'قطع الغيار'],
            'protection-films' => ['Protection films', 'أفلام الحماية'], 'paint-protection-film' => ['Paint protection film', 'فيلم حماية الطلاء'], 'ppf' => ['PPF', 'بي بي إف'],
            'coatings-and-detailing' => ['Coatings and detailing', 'الطلاءات والتلميع'], 'ceramic-coating' => ['Ceramic coating', 'طلاء سيراميك'],
            'interior-care' => ['Interior care', 'العناية بالداخلية'], 'brakes' => ['Brakes', 'الفرامل'], 'protection' => ['Protection', 'الحماية'], 'care-kits' => ['Care kits', 'حقائب العناية'],
        ]],
        'beauty' => ['Beauty and care', 'الجمال والعناية', [
            'natural-care' => ['Natural care', 'عناية طبيعية'], 'face-and-body' => ['Face and body', 'الوجه والجسم'], 'hair-and-nails' => ['Hair and nails', 'الشعر والأظافر'],
            'facial' => ['Facial', 'عناية بالوجه'], 'skin-care' => ['Skin care', 'العناية بالبشرة'], 'hair-care' => ['Hair care', 'العناية بالشعر'],
        ]],
        'home' => ['Home and living', 'المنزل والمعيشة', [
            'home-services' => ['Home services', 'خدمات منزلية'], 'home-textiles' => ['Home textiles', 'منسوجات منزلية'], 'handicrafts' => ['Handicrafts', 'حرف يدوية'],
            'grocery' => ['Grocery', 'بقالة'], 'gifts' => ['Gifts', 'هدايا'], 'repairs' => ['Repairs', 'إصلاحات'],
        ]],
        'devices' => ['Devices and electronics', 'الأجهزة والإلكترونيات', [
            'smart-home' => ['Smart home', 'المنزل الذكي'], 'electronics' => ['Electronics', 'إلكترونيات'], 'photos' => ['Photos', 'صور'],
        ]],
        'other' => ['Other', 'أخرى', ['misc' => ['Miscellaneous', 'متنوعة'], 'x' => ['General', 'عام']]],
    ];

    public static function ensureDefault(): void
    {
        $pos = 0;
        foreach (self::DEFAULT as $key => [$en, $ar, $children]) {
            $group = ProductCategory::firstOrCreate(['key' => $key], ['name_en' => $en, 'name_ar' => $ar, 'position' => $pos += 10]);
            $i = 0;
            foreach ($children as $ck => [$cen, $car]) {
                ProductCategory::firstOrCreate(['key' => $ck], ['parent_id' => $group->id, 'name_en' => $cen, 'name_ar' => $car, 'position' => $i += 10]);
            }
        }
    }

    /** A category a product already uses but the taxonomy does not know yet is filed under "Other", never lost. */
    public static function adopt(string $key): ProductCategory
    {
        $key = Str::slug($key);

        return ProductCategory::firstOrCreate(['key' => $key], [
            'parent_id' => ProductCategory::where('key', 'other')->value('id'),
            'name_en' => Str::title(str_replace('-', ' ', $key)), 'name_ar' => Str::title(str_replace('-', ' ', $key)), 'position' => 100,
        ]);
    }
}
