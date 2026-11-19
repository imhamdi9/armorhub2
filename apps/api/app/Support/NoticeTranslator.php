<?php

namespace App\Support;

/**
 * Notifications are stored in the language their reader had when they were sent. Older ones (and ones sent to someone who read English then)
 * are shown in Arabic when the reader now reads Arabic, whenever lang/ar.json knows the sentence: word for word, or with its ":placeholders" filled from the English text.
 */
class NoticeTranslator
{
    /** @var array<string, string>|null */
    private static ?array $ar = null;

    /** @var list<array{0: string, 1: string, 2: list<string>}>|null  regex, Arabic text, placeholder names */
    private static ?array $patterns = null;

    public static function toArabic(string $text): string
    {
        if ($text === '' || preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }
        self::load();
        if (isset(self::$ar[$text])) {
            return self::$ar[$text];
        }
        foreach (self::$patterns as [$regex, $arabic, $names]) {
            if (preg_match($regex, $text, $m)) {
                foreach ($names as $name) {
                    $arabic = str_replace(':'.$name, $m[$name] ?? '', $arabic);
                }

                return $arabic;
            }
        }

        return $text;
    }

    private static function load(): void
    {
        if (self::$ar !== null) {
            return;
        }
        self::$ar = (array) json_decode((string) @file_get_contents(base_path('lang/ar.json')), true);
        self::$patterns = [];
        foreach (self::$ar as $english => $arabic) {
            if (! preg_match_all('/:([a-z_]+)/', (string) $english, $found) || $arabic === $english) {
                continue;
            }
            $names = array_values(array_unique($found[1]));
            $regex = preg_quote((string) $english, '/');
            foreach ($names as $name) {
                $regex = preg_replace('/\\\\:'.$name.'/', '(?P<'.$name.'>.+?)', $regex, 1) ?? $regex;
                $regex = str_replace('\\:'.$name, '(?P=' .$name.')', $regex);
            }
            self::$patterns[] = ['/^'.$regex.'$/su', (string) $arabic, $names];
        }
        // The longest sentence first, so a short pattern never swallows a longer one.
        usort(self::$patterns, fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    }
}
