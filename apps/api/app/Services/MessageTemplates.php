<?php

namespace App\Services;

use App\Core\Surfaces\SurfaceStore;
use Throwable;

/**
 * The wording of notifications and emails. Admins may publish their own title and text per message kind
 * (surface app.messages); {placeholders} are filled in. Anything unset, or any failure, keeps the built-in wording.
 */
class MessageTemplates
{
    public const KINDS = [
        'quote_requested', 'quote_received', 'quote_accepted', 'job_ready', 'order_to_ship', 'payment_received', 'scheduled', 'started',
        'completed', 'shipped', 'shipment_out_for_delivery', 'shipment_delivered', 'shipment_failed', 'cancelled', 'payout_paid',
    ];

    /** @var array<string, array{title?: string, body?: string}>|null */
    private ?array $cache = null;

    public function __construct(private SurfaceStore $surfaces)
    {
    }

    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{0: string, 1: string} title and body
     */
    public function render(string $kind, string $title, string $body, array $vars): array
    {
        $template = $this->templates()[$kind] ?? [];

        return [
            $this->fill($template['title'] ?? '', $vars) ?: $title,
            $this->fill($template['body'] ?? '', $vars) ?: $body,
        ];
    }

    /** @param array<string, scalar|null> $vars */
    private function fill(string $text, array $vars): string
    {
        if (trim($text) === '') {
            return '';
        }

        return trim(preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text));
    }

    /** @return array<string, array{title?: string, body?: string}> */
    private function templates(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $rows = $this->surfaces->published('app.messages', 'en')['templates'] ?? [];
        } catch (Throwable) {
            $rows = [];
        }

        return $this->cache = collect($rows)->filter(fn ($r) => is_array($r) && isset($r['kind']))->mapWithKeys(fn ($r) => [$r['kind'] => $r])->all();
    }
}
