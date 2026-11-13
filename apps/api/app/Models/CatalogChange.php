<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogChange extends Model
{
    protected $fillable = ['tenant_id', 'requested_by', 'target', 'action', 'record_id', 'payload', 'summary', 'status', 'decided_by', 'decided_at', 'note'];

    protected $casts = ['payload' => 'array', 'decided_at' => 'datetime'];
}
