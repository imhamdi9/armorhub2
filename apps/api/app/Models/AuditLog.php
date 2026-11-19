<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only: rows are created and read, never edited. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent', 'meta'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }
}
