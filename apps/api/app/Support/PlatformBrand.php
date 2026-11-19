<?php

namespace App\Support;

use App\Core\Surfaces\SurfaceStore;

/** The platform's own name, as its owners set it in the theme editor (ArmorHub until they change it). */
class PlatformBrand
{
    public static function name(string $locale = 'en'): string
    {
        try {
            $store = app(SurfaceStore::class);
            $name = trim((string) ($store->published('site.brand', $locale)['name'] ?? ''));
            if ($name === '' || ($locale !== 'en' && $name === 'ArmorHub')) {
                $name = trim((string) ($store->published('site.brand', 'en')['name'] ?? ''));
            }

            return $name !== '' ? $name : 'ArmorHub';
        } catch (\Throwable) {
            return 'ArmorHub';
        }
    }

    /** A sentence with the platform's own name in place of the built-in one. */
    public static function in(string $text): string
    {
        $name = self::name(app()->getLocale() === 'ar' ? 'ar' : 'en');

        return $name === 'ArmorHub' ? $text : str_replace(['ArmorHub', 'أرمور هب'], $name, $text);
    }
}
