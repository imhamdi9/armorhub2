<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail to someone who bought without an account: the news, and the secret link to their order. */
class GuestOrderUpdate extends Notification
{
    public function __construct(public string $title, public string $body, public string $token, ?string $language = null)
    {
        // The e-mail is written in the language of the page the guest ordered from (the framework keeps it in $this->locale).
        if (in_array($language, ['ar', 'en'], true)) {
            $this->locale($language);
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting(__('Hello,'))->line($this->body)
            ->action(__('See your order'), rtrim(config('platform.web_url'), '/').'/track/'.$this->token)
            ->line(__('Keep this e-mail: the button is your private link to follow the order. You can also create an account with this e-mail address to see all your orders in one place.'));
    }
}
