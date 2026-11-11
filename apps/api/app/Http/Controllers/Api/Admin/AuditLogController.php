<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['per_page' => 'nullable|integer|min:1|max:100']);

        return AuditLog::with('user:id,name,email')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('ip'), fn ($q) => $q->where('ip', $request->ip))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($w) => $w->where('action', 'like', $term)->orWhere('meta', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50))
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'user' => $l->user?->only(['id', 'name', 'email']),
                'subject' => $l->subject_type ? "{$l->subject_type}#{$l->subject_id}" : null,
                'ip' => $l->ip,
                'meta' => $l->meta,
                'created_at' => $l->created_at,
            ]);
    }
}
