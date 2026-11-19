<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes the audit trail. Logging must never break the action being logged. */
class Audit
{
    public static function record(string $action, ?Model $subject = null, array $meta = [], ?User $actor = null): void
    {
        try {
            $request = app()->bound('request') ? request() : null;
            $actor ??= auth()->user();

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'meta' => $meta ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
