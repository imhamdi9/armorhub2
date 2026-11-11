<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells reviewers that a business wants to join as a partner. */
class PartnerApplicationReceived extends Notification
{
    public function __construct(public string $business, public string $store)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'partner_application',
            'title' => 'New partner application',
            'body' => "{$this->business} applied to join {$this->store} as a partner.",
            'path' => '/dashboard/partner-applications',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New partner application')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->business} applied to join {$this->store} as a partner.")
            ->action('Review applications', rtrim(config('platform.web_url'), '/').'/dashboard/partner-applications');
    }
}
