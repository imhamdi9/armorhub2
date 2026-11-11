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

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => 'payout_paid', 'title' => 'Payout sent', 'body' => "{$this->amount} was paid out to you (reference {$this->reference}).", 'path' => '/dashboard/earnings'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Payout sent')->greeting("Hello {$notifiable->name},")->line("{$this->amount} was paid out to you (reference {$this->reference}).")
            ->action('See your earnings', rtrim(config('platform.web_url'), '/').'/dashboard/earnings');
    }
}
