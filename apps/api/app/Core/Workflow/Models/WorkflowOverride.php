<?php

namespace App\Core\Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowOverride extends Model
{
    protected $table = 'workflow_overrides';

    protected $guarded = [];

    protected $casts = ['definition' => 'array', 'active' => 'boolean'];
}
