<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $guarded = [];

    protected $casts = ['replied_at' => 'datetime'];

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('id');
    }
}
