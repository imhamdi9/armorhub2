<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\InstallationNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Asking ArmorHub for help, and reading the answer. */
class SupportController extends Controller
{
    private const MAX_OPEN = 5;

    public function __construct(private InstallationNotifier $notify)
    {
    }

    public function index(Request $request)
    {
        return SupportTicket::where('user_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($t) => $this->present($t))->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['subject' => 'required|string|min:3|max:120', 'body' => 'required|string|min:5|max:2000']);
        $user = $request->user();

        if (SupportTicket::where('user_id', $user->id)->where('status', 'open')->count() >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['subject' => ['You have several open questions already. We will answer them first.']]);
        }
        $ticket = SupportTicket::create(['user_id' => $user->id, 'subject' => trim(strip_tags($data['subject'])), 'body' => trim(strip_tags($data['body']))]);
        $this->notify->admins('New help request', mb_substr($ticket->subject, 0, 100), '/dashboard/admin/tools');

        return response()->json($this->present($ticket->fresh()), 201);
    }

    public function close(Request $request, int $id)
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return $this->present($ticket->fresh());
    }

    /** @return array<string, mixed> */
    private function present(SupportTicket $t): array
    {
        return ['id' => $t->id, 'subject' => $t->subject, 'body' => $t->body, 'status' => $t->status, 'reply' => $t->reply, 'replied_at' => $t->replied_at?->toIso8601String(), 'created_at' => $t->created_at?->toIso8601String()];
    }
}
