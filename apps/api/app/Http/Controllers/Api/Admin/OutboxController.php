<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/** The e-mails kept by the "outbox" mailer: what the platform would have sent. */
class OutboxController extends Controller
{
    public function index()
    {
        return DB::table('mail_outbox')->latest('id')->limit(100)->get(['id', 'to', 'subject', 'created_at']);
    }

    public function show(int $id)
    {
        return DB::table('mail_outbox')->where('id', $id)->first(['id', 'to', 'subject', 'html', 'text', 'created_at']) ?? abort(404);
    }
}
