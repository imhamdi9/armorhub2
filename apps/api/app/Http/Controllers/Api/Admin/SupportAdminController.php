<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InstallationNotifier;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Help requests waiting for an answer. */
class SupportAdminController extends Controller
{
    public function index(Request $request)
    {
        return SupportTicket::where('status', $request->query('status', 'open'))->oldest('id')->limit(100)->get()->map(fn ($t) => [
            'id' => $t->id, 'subject' => $t->subject, 'body' => $t->body, 'status' => $t->status, 'customer' => User::withoutGlobalScopes()->whereKey($t->user_id)->value('name'), 'created_at' => $t->created_at?->toIso8601String(),
        ])->values();
    }

    public function reply(Request $request, int $id, InstallationNotifier $notify)
    {
        $data = $request->validate(['reply' => 'required|string|min:2|max:2000']);
        $ticket = SupportTicket::where('status', 'open')->findOrFail($id);
        $ticket->update(['reply' => trim(strip_tags($data['reply'])), 'status' => 'answered', 'replied_by' => $request->user()->id, 'replied_at' => now()]);
        $notify->user($ticket->user_id, 'ArmorHub answered your question', mb_substr($ticket->subject, 0, 100), '/dashboard/help');
        Audit::record('support.replied', $ticket, [], $request->user());

        return ['id' => $ticket->id, 'status' => $ticket->status];
    }
}
