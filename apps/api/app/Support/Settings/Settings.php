<?php

namespace App\Support\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Read and write runtime settings. Reads fall back to each definition's default
 * (which itself comes from config/env), so the app works with an empty table.
 */
class Settings
{
    private const CACHE_KEY = 'platform.settings.v1';
    public const MASK = '********';

    private ?array $memo = null;

    /** The market (tenant id) whose overrides apply right now: null means the platform-wide values. */
    private ?int $market = null;

    /** Settings a market may set for itself. Everything else stays platform-wide. */
    public const MARKET_PREFIXES = ['payments.', 'shipping.', 'installation.', 'platform.commission'];

    public static function overridable(string $key): bool
    {
        foreach (self::MARKET_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function market(): ?int
    {
        return $this->market;
    }

    /** Reads (and writes) now apply to this market. Pass null to go back to the platform-wide values. */
    public function enterMarket(?int $tenantId): void
    {
        if ($this->market !== $tenantId) {
            $this->market = $tenantId;
            $this->memo = null;
        }
    }

    /** Runs something as one market (a payment for that market's order), then restores whatever was active. */
    public function inMarket(?int $tenantId, \Closure $fn): mixed
    {
        $before = $this->market;
        $this->enterMarket($tenantId);
        try {
            return $fn();
        } finally {
            $this->enterMarket($before);
        }
    }

    /** Inside a market only the market-overridable settings may be written, and they are written for that market alone. */
    private function storageKeyForWrite(string $key): string
    {
        if ($this->market === null) {
            return $key;
        }
        if (! self::overridable($key)) {
            throw new \InvalidArgumentException("The setting [{$key}] is platform-wide and cannot be changed for one market.");
        }

        return "{$key}@t{$this->market}";
    }

    /** True when this market has its own value for the setting (as opposed to inheriting the platform-wide one). */
    public function overriddenHere(string $key): bool
    {
        if ($this->market === null) {
            return false;
        }

        return array_key_exists("{$key}@t{$this->market}", $this->rawRows());
    }

    /** @return array<string, array{value: mixed, secret: bool}> */
    private function rawRows(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => Setting::all()
                ->mapWithKeys(fn (Setting $r) => [$r->key => ['value' => $r->value, 'secret' => (bool) $r->is_secret]])
                ->all());
        } catch (QueryException) {
            return [];
        }
    }

    public function __construct(private SettingsRegistry $registry)
    {
    }

    public function get(string $key): mixed
    {
        $definition = $this->registry->get($key);
        $raw = $this->stored();

        if (! array_key_exists($key, $raw)) {
            return $definition->defaultValue();
        }

        return $definition->cast($raw[$key]);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /** True when an admin set a value (as opposed to the built-in default being used). */
    public function isCustomised(string $key): bool
    {
        return array_key_exists($key, $this->stored());
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $definition = $this->registry->get($key);

        // An empty box means "use the default" (casting '' first would turn a number into 0).
        $value = $value === '' ? null : $definition->cast($value);

        if ($value === null || $value === '') {
            $this->forget($key, $by);

            return;
        }

        $payload = json_encode($value);

        Setting::updateOrCreate(['key' => $this->storageKeyForWrite($key)], [
            'value' => $definition->isSecret() ? Crypt::encryptString($payload) : $payload,
            'is_secret' => $definition->isSecret(),
            'updated_by' => $by?->id,
        ]);

        $this->flush();

        // Never write secret values to the audit log.
        Audit::record('settings.updated', null, ['key' => $key, 'market' => $this->market, 'value' => $definition->isSecret() ? '(secret changed)' : $value], $by);
    }

    /** Remove the admin's value so the default applies again. */
    public function forget(string $key, ?User $by = null): void
    {
        if (Setting::where('key', $this->storageKeyForWrite($key))->delete()) {
            Audit::record('settings.reset', null, ['key' => $key, 'market' => $this->market], $by);
        }

        $this->flush();
    }

    /**
     * Definitions plus current values for the admin UI. Secrets are never returned,
     * only whether one is set.
     *
     * @return list<array<string, mixed>>
     */
    public function describe(?string $group = null): array
    {
        $rows = [];

        foreach ($this->registry->all() as $definition) {
            if ($group !== null && $definition->group !== $group) {
                continue;
            }

            $rows[] = [
                'key' => $definition->key,
                'group' => $definition->group,
                'label' => $definition->label,
                'type' => $definition->type,
                'help' => $definition->help,
                'options' => $definition->options,
                'value' => $definition->isSecret() ? null : $this->get($definition->key),
                'has_value' => $definition->isSecret() ? ($this->market !== null ? $this->overriddenHere($definition->key) : $this->isCustomised($definition->key)) : null,
                'is_default' => ! $this->isCustomised($definition->key),
                // In a market: whether the market has its own value; otherwise it uses the platform-wide one.
                'market_override' => $this->market !== null ? $this->overriddenHere($definition->key) : null,
                'market_can_override' => self::overridable($definition->key),
            ];
        }

        return $rows;
    }

    public function registry(): SettingsRegistry
    {
        return $this->registry;
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * key => decoded value. Only the raw rows (secrets still encrypted) go in the shared cache;
     * decryption happens in memory, so a cache or database dump never exposes a secret.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        // (No database or settings table yet, e.g. a CLI command before `migrate`: every setting has its default.)
        $rows = $this->rawRows();

        $values = [];

        // A market's own values (stored as "key@t<market>") win over the platform-wide ones, but only for that market.
        $mine = $this->market !== null ? "@t{$this->market}" : null;
        $folded = [];
        foreach ($rows as $key => $row) {
            if (($at = strpos($key, '@t')) !== false) {
                if ($mine !== null && substr($key, $at) === $mine) {
                    $folded[substr($key, 0, $at)] = $row;
                }

                continue;
            }
            $folded[$key] ??= $row;
        }
        foreach ($rows as $key => $row) {
            if (($at = strpos($key, '@t')) !== false && $mine !== null && substr($key, $at) === $mine) {
                $folded[substr($key, 0, $at)] = $row;
            }
        }
        $rows = $folded;

        foreach ($rows as $key => $row) {
            try {
                $json = $row['secret'] ? Crypt::decryptString((string) $row['value']) : $row['value'];
                $values[$key] = json_decode((string) $json, true);
            } catch (DecryptException $e) {
                // The key changed since this was saved: treat the secret as unset rather than crash.
                report($e);
            }
        }

        return $this->memo = $values;
    }
}
