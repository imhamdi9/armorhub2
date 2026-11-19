<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/** Records create/update/delete of important records, with what changed (never secrets). */
trait Auditable
{
    /** Attributes never written to the log. */
    private static array $auditHidden = ['password', 'remember_token', 'value', 'token'];

    protected static function bootAuditable(): void
    {
        static::created(fn ($m) => Audit::record(self::auditName($m).'.created', $m, self::auditClean($m->getAttributes())));

        static::updated(function ($m) {
            $changes = collect($m->getChanges())->except(['updated_at'])->all();

            if ($changes) {
                Audit::record(self::auditName($m).'.updated', $m, self::auditClean($changes));
            }
        });

        static::deleted(fn ($m) => Audit::record(self::auditName($m).'.deleted', $m));
    }

    private static function auditName($model): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($model)));
    }

    private static function auditClean(array $attributes): array
    {
        $clean = array_diff_key($attributes, array_flip(self::$auditHidden));

        // A password change is worth recording, but never its value.
        if (array_key_exists('password', $attributes)) {
            $clean['password'] = '(changed)';
        }

        return array_map(fn ($v) => is_string($v) && mb_strlen($v) > 200 ? mb_substr($v, 0, 200).'…' : $v, $clean);
    }
}
