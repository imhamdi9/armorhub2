<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationSent extends Notification
{
    public function __construct(private string $inviter, private string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("{$this->inviter} invited you to ArmorHub")
            ->line("{$this->inviter} invited you to join their team on ArmorHub. The link works for 7 days.")
            ->action('Accept the invitation', rtrim(config('platform.web_url'), '/').'/invitation/'.$this->token);
    }
}
