<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $this->say($n->data['title'] ?? ''),
                'body' => $this->say($n->data['body'] ?? ''),
                'path' => $n->data['path'] ?? '/dashboard',
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at,
            ]),
        ];
    }

    /** An older notice written in English reads in Arabic for someone who now reads Arabic (when the sentence is known). */
    private function say(string $text): string
    {
        return app()->getLocale() === 'ar' ? \App\Support\NoticeTranslator::toArabic($text) : $text;
    }

    /** Just the number, cheap enough to poll. */
    public function unread(Request $request)
    {
        return ['unread' => $request->user()->unreadNotifications()->count()];
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All marked as read']);
    }
}
