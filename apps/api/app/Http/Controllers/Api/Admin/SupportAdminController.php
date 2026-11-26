<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InstallationNotifier;
use App\Support\Audit;
use App\Support\MarketScope;
use Illuminate\Http\Request;

/** Help requests and their conversations, for ArmorHub's support team. A market's agent sees only that market's customers. */
class SupportAdminController extends Controller
{
    /** @return \Illuminate\Database\Eloquent\Builder<SupportTicket> */
    private function scoped(Request $request)
    {
        $ids = MarketScope::companyIds($request->user());

        return SupportTicket::query()->when($ids !== null, fn ($q) => $q->whereIn('user_id', User::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')));
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'open');

        return $this->scoped($request)->with('messages')->where('status', $status)->oldest('id')->limit(100)->get()->map(fn ($t) => [
            'id' => $t->id, 'subject' => $t->subject, 'body' => $t->body, 'status' => $t->status, 'order_id' => $t->order_id,
            'customer' => User::withoutGlobalScopes()->whereKey($t->user_id)->value('name'), 'created_at' => $t->created_at?->toIso8601String(),
            'messages' => $t->messages->map(fn ($m) => ['id' => $m->id, 'side' => $m->side, 'body' => $m->body, 'created_at' => $m->created_at?->toIso8601String()])->values(),
        ])->values();
    }

    public function reply(Request $request, int $id, InstallationNotifier $notify)
    {
        $data = $request->validate(['reply' => 'required|string|min:2|max:2000']);
        $ticket = $this->scoped($request)->whereIn('status', ['open', 'answered'])->findOrFail($id);
        $body = trim(strip_tags($data['reply']));
        SupportMessage::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'side' => 'staff', 'body' => $body]);
        $ticket->update(['reply' => $body, 'status' => 'answered', 'replied_by' => $request->user()->id, 'replied_at' => now()]);
        $notify->user($ticket->user_id, 'ArmorHub answered your question', mb_substr($ticket->subject, 0, 100), '/dashboard/help');
        Audit::record('support.replied', $ticket, [], $request->user());

        return ['id' => $ticket->id, 'status' => $ticket->status];
    }
}
