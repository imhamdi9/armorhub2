<?php

namespace App\Support;

/**
 * Keeps contact details out of text that is shown before payment, so a deal cannot be taken off the platform.
 * Catches phone numbers (also Arabic-Indic digits), e-mail addresses, links and messaging-app hints.
 */
final class ContactGuard
{
    public static function contains(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        $t = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $digits = preg_replace('/\D+/', '', $t) ?? '';

        return strlen($digits) >= 7
            || (bool) preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $t)
            || (bool) preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|eg|sa|me|io)\b)~i', $t)
            || (bool) preg_match('/whats\s*app|واتس|telegram|تيليجرام|تليجرام|instagram|انستجرام|فيسبوك|facebook|call me|اتصل بي|كلمني/iu', $t);
    }
}
