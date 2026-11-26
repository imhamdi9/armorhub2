<?php

namespace App\Support;

/** An image reference admins and sellers may store: an https address, or a file uploaded to the platform (/media/<name>). */
final class ImageRef
{
    public static function valid(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 500) {
            return false;
        }

        return (bool) (preg_match('~^https://[^\s<>"\']+$~i', $value)
            || preg_match('~^/media/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(png|jpg|webp|gif)$~', $value));
    }
}
