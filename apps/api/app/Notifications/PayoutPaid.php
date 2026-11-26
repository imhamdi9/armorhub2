<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaid extends Notification
{
    public function __construct(public string $amount, public string $reference)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{0: string, 1: string} */
    private function words(): array
    {
        return app(\App\Services\MessageTemplates::class)->render('payout_paid', 'Payout sent',
            "{$this->amount} was paid out to you (reference {$this->reference}).", ['amount' => $this->amount, 'reference' => $this->reference]);
    }

    public function toDatabase(object $notifiable): array
    {
        [$title, $body] = $this->words();

        return ['kind' => 'payout_paid', 'title' => $title, 'body' => $body, 'path' => '/dashboard/earnings'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$title, $body] = $this->words();

        return (new MailMessage)->subject($title)->greeting("Hello {$notifiable->name},")->line($body)
            ->action('See your earnings', rtrim(config('platform.web_url'), '/').'/dashboard/earnings');
    }
}
