<?php

namespace App\Services\Storefront;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * What makes a shop's search forgiving: a misspelt word is read as the closest word the shop really uses ("brke pads" finds "Brake pads"),
 * suggestions come as you type, and the filters shoppers expect (price, brand, rating) narrow the same list.
 */
class ProductSearch
{
    /** Price a shopper pays at the least: the cheapest active price rule, or the base price when there are none. */
    public const LOWEST_PRICE = '(select coalesce(min(r.price), products.base_price) from product_price_rules r where r.product_id = products.id and r.is_active = 1)';

    public const AVERAGE_RATING = "(select avg(v.rating) from product_reviews v where v.product_id = products.id and v.status = 'published')";

    /** @return list<string> the lower-case words the shop's products use, most useful first */
    public function vocabulary(Builder $base): array
    {
        $key = 'search-vocab:'.md5($base->toSql().json_encode($base->getBindings()));

        return Cache::remember($key, 300, function () use ($base) {
            $words = [];
            foreach ((clone $base)->limit(1500)->get(['name', 'brand', 'category']) as $p) {
                foreach ([$p->name, $p->brand, str_replace('-', ' ', (string) $p->category)] as $text) {
                    foreach ($this->tokens((string) $text) as $w) {
                        $words[$w] = ($words[$w] ?? 0) + 1;
                    }
                }
            }
            arsort($words);

            return array_map('strval', array_keys($words));
        });
    }

    /**
     * The query with each unknown word replaced by its closest known word; null when nothing was close enough to change.
     *
     * @param  list<string>  $vocabulary
     */
    public function correct(string $query, array $vocabulary): ?string
    {
        $known = array_flip($vocabulary);
        $changed = false;
        $out = [];

        foreach ($this->tokens($query) as $word) {
            if (isset($known[$word]) || mb_strlen($word) < 4) {
                $out[] = $word;

                continue;
            }
            $allowed = mb_strlen($word) >= 8 ? 2 : 1;
            $best = null;
            $bestDistance = $allowed + 1;
            foreach ($vocabulary as $candidate) {
                if (abs(mb_strlen($candidate) - mb_strlen($word)) > $allowed) {
                    continue;
                }
                $d = $this->distance($word, $candidate);
                if ($d < $bestDistance) {
                    [$best, $bestDistance] = [$candidate, $d];
                }
            }
            $out[] = $best ?? $word;
            $changed = $changed || $best !== null;
        }

        return $changed ? implode(' ', $out) : null;
    }

    /**
     * Up to $limit things to type next: product names, brands and categories that contain what was typed (starting with it first).
     *
     * @return list<array{text: string, kind: string}>
     */
    public function suggest(Builder $base, string $query, int $limit = 8): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = (clone $base)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('name_ar', 'like', $like)->orWhere('brand', 'like', $like))->limit(60)->get(['name', 'name_ar', 'brand', 'category']);
        $starts = fn (string $s) => mb_stripos($s, $q) === 0 ? 0 : 1;

        $found = [];
        foreach ($rows as $p) {
            $found['product|'.mb_strtolower($p->name)] = ['text' => $p->name, 'kind' => 'product'];
            if ($p->brand && mb_stripos($p->brand, $q) !== false) {
                $found['brand|'.mb_strtolower($p->brand)] = ['text' => $p->brand, 'kind' => 'brand'];
            }
        }
        $found = array_values($found);
        usort($found, fn ($a, $b) => [$starts($a['text']), $a['kind'] === 'brand' ? 0 : 1, $a['text']] <=> [$starts($b['text']), $b['kind'] === 'brand' ? 0 : 1, $b['text']]);

        // Nothing contains it: it is probably a typo, so offer the corrected words.
        if ($found === [] && ($fixed = $this->correct($q, $this->vocabulary($base)))) {
            $again = (clone $base)->where('name', 'like', '%'.addcslashes(explode(' ', $fixed)[0], '%_\\').'%')->limit($limit)->pluck('name');
            $found = $again->map(fn ($n) => ['text' => $n, 'kind' => 'product'])->all();
        }

        return array_slice($found, 0, $limit);
    }

    /** Brands with how many products each has, and the price range: what the filters offer. @return array{brands: list<array{name: string, count: int}>, price: array{min: float, max: float}} */
    public function facets(Builder $base): array
    {
        $brands = (clone $base)->whereNotNull('brand')->where('brand', '!=', '')->reorder()->selectRaw('brand, count(*) as c')->groupBy('brand')->orderByDesc('c')->orderBy('brand')->limit(30)->get()
            ->map(fn ($r) => ['name' => $r->brand, 'count' => (int) $r->c])->all();
        $price = (clone $base)->reorder()->selectRaw('min('.self::LOWEST_PRICE.') as lo, max('.self::LOWEST_PRICE.') as hi')->first();

        return ['brands' => $brands, 'price' => ['min' => (float) ($price->lo ?? 0), 'max' => (float) ($price->hi ?? 0)]];
    }

    /** @return list<string> */
    public function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);

        return $m[0];
    }

    /** Edit distance counted in letters (so Arabic words are measured like any other). */
    private function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $prev = range(0, count($b));
        foreach ($a as $i => $ca) {
            $row = [$i + 1];
            foreach ($b as $j => $cb) {
                $row[] = min($prev[$j + 1] + 1, $row[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }
            $prev = $row;
        }

        return $prev[count($b)];
    }
}
