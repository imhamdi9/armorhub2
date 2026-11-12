<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = ['token_hash', 'email', 'role', 'invited_by', 'company_id', 'partner_id', 'department_id', 'as_head', 'expires_at', 'accepted_at'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'as_head' => 'boolean'];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
