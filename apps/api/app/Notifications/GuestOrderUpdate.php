<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail to someone who bought without an account: the news, and the secret link to their order. */
class GuestOrderUpdate extends Notification
{
    public function __construct(public string $title, public string $body, public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting('Hello,')->line($this->body)
            ->action('See your order', rtrim(config('platform.web_url'), '/').'/track/'.$this->token)
            ->line('Keep this e-mail: the button is your private link to follow the order. You can also create an account with this e-mail address to see all your orders in one place.');
    }
}
