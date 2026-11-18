<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\InstallationNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Asking ArmorHub for help (about an order or anything else) and following the conversation until it is closed. */
class SupportController extends Controller
{
    private const MAX_OPEN = 5;

    public function __construct(private InstallationNotifier $notify)
    {
    }

    public function index(Request $request)
    {
        return SupportTicket::with('messages')->where('user_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($t) => $this->present($t))->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['subject' => 'required|string|min:3|max:120', 'body' => 'required|string|min:5|max:2000', 'order_id' => 'nullable|integer']);
        $user = $request->user();

        if (SupportTicket::where('user_id', $user->id)->where('status', 'open')->count() >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['subject' => ['You have several open questions already. We will answer them first.']]);
        }
        // A question about an order must be about one of your own.
        if (! empty($data['order_id']) && ! Order::withoutGlobalScopes()->where('customer_id', $user->id)->whereKey($data['order_id'])->exists()) {
            throw ValidationException::withMessages(['order_id' => ['This order was not found.']]);
        }
        $body = trim(strip_tags($data['body']));
        $ticket = SupportTicket::create(['user_id' => $user->id, 'subject' => trim(strip_tags($data['subject'])), 'body' => $body, 'order_id' => $data['order_id'] ?? null]);
        SupportMessage::create(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'side' => 'customer', 'body' => $body]);
        $this->notify->admins('New help request', mb_substr($ticket->subject, 0, 100), '/dashboard/admin/support');

        return response()->json($this->present($ticket->fresh('messages')), 201);
    }

    /** The customer writes again: a closed or answered conversation is open again. */
    public function message(Request $request, int $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $body = trim(strip_tags($request->validate(['body' => 'required|string|min:2|max:2000'])['body']));
        if ($ticket->status === 'closed' && now()->diffInDays($ticket->updated_at, true) > 14) {
            throw ValidationException::withMessages(['body' => ['This conversation was closed a while ago. Please start a new one.']]);
        }
        SupportMessage::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'side' => 'customer', 'body' => $body]);
        $ticket->update(['status' => 'open']);
        $this->notify->admins('New message in a help request', mb_substr($ticket->subject, 0, 100), '/dashboard/admin/support');

        return $this->present($ticket->fresh('messages'));
    }

    public function close(Request $request, int $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return $this->present($ticket->fresh('messages'));
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $t): array
    {
        $messages = $t->messages->map(fn ($m) => ['id' => $m->id, 'side' => $m->side, 'body' => $m->body, 'created_at' => $m->created_at?->toIso8601String()])->values();

        return ['id' => $t->id, 'subject' => $t->subject, 'body' => $t->body, 'status' => $t->status, 'order_id' => $t->order_id, 'messages' => $messages,
            // Older single-reply field, kept for screens that still read it: the last answer from ArmorHub.
            'reply' => $t->messages->where('side', 'staff')->last()?->body ?? $t->reply, 'replied_at' => $t->replied_at?->toIso8601String(), 'created_at' => $t->created_at?->toIso8601String()];
    }
}
