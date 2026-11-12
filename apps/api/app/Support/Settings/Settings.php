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

        Setting::updateOrCreate(['key' => $key], [
            'value' => $definition->isSecret() ? Crypt::encryptString($payload) : $payload,
            'is_secret' => $definition->isSecret(),
            'updated_by' => $by?->id,
        ]);

        $this->flush();

        // Never write secret values to the audit log.
        Audit::record('settings.updated', null, ['key' => $key, 'value' => $definition->isSecret() ? '(secret changed)' : $value], $by);
    }

    /** Remove the admin's value so the default applies again. */
    public function forget(string $key, ?User $by = null): void
    {
        if (Setting::where('key', $key)->delete()) {
            Audit::record('settings.reset', null, ['key' => $key], $by);
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
                'has_value' => $definition->isSecret() ? $this->isCustomised($definition->key) : null,
                'is_default' => ! $this->isCustomised($definition->key),
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

        try {
            $rows = Cache::remember(self::CACHE_KEY, 300, fn () => Setting::all()
                ->mapWithKeys(fn (Setting $r) => [$r->key => ['value' => $r->value, 'secret' => (bool) $r->is_secret]])
                ->all());
        } catch (QueryException) {
            // No database (or no settings table) yet, e.g. a CLI command run before `migrate`:
            // every setting simply has its default. Nothing is cached, so it recovers on its own.
            return $this->memo = [];
        }

        $values = [];

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
