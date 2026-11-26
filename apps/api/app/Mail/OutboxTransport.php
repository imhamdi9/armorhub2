<?php

namespace App\Mail;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/** The "outbox" mailer: keeps every e-mail in the database instead of sending it, so it can be read (development, demos and checking wording). */
class OutboxTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email) {
            return;
        }

        DB::table('mail_outbox')->insert([
            'to' => implode(', ', array_map(fn ($a) => $a->getAddress(), $email->getTo())),
            'subject' => mb_substr((string) $email->getSubject(), 0, 255),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function __toString(): string
    {
        return 'outbox://database';
    }
}
