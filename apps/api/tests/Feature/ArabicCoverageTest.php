<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Everything the API says to a person (error messages, notification titles and bodies) must have its Arabic in lang/ar.json,
 * so the platform in Arabic never shows an English sentence. Sentences built by joining text and numbers must use :placeholders.
 */
class ArabicCoverageTest extends TestCase
{
    private const STRING = '(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")';

    public function test_every_sentence_the_api_sends_to_people_has_an_arabic_translation(): void
    {
        $ar = json_decode(file_get_contents(base_path('lang/ar.json')), true);
        $patterns = [
            '/withMessages\(\s*\[[^\]]*?=>\s*\[\s*'.self::STRING.'/s',
            '/new SimpleNotice\(\s*'.self::STRING.'\s*,\s*'.self::STRING.'/',
            '/->(?:user|admins|centre)\([^,]*?,\s*'.self::STRING.'\s*,\s*'.self::STRING.'/',
        ];
        $skip = ['/^[a-z_.*]+$/', '/^[a-z-]+$/'];
        $missing = [];
        $checked = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $source, $found, PREG_SET_ORDER);
                foreach ($found as $m) {
                    foreach (array_slice($m, 1) as $text) {
                        $text = str_replace(["\\'", '\\"'], ["'", '"'], (string) $text);
                        if ($text === '' || ! preg_match('/[A-Za-z]{3}/', $text) || str_starts_with($text, '/') || str_contains($text, '{$')) {
                            continue;
                        }
                        foreach ($skip as $re) {
                            if (preg_match($re, $text)) {
                                continue 2;
                            }
                        }
                        $checked++;
                        if (! array_key_exists($text, $ar)) {
                            $missing[$text] = basename($file->getPathname());
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(80, $checked, 'The scan found almost nothing: its patterns need updating.');
        $list = collect($missing)->map(fn ($f, $t) => "{$f}: {$t}")->values()->implode("\n");
        $this->assertSame([], array_keys($missing), "These sentences have no Arabic in lang/ar.json:\n{$list}");
    }
}
