<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    protected $fillable = ['application_id', 'kind', 'original_name', 'path', 'mime', 'size', 'sha256'];

    /** The stored path is an internal detail, not something to hand to a browser. */
    protected $hidden = ['path', 'sha256'];

    public function application()
    {
        return $this->belongsTo(PartnerApplication::class, 'application_id');
    }
}
