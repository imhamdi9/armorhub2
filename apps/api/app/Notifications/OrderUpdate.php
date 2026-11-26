<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One in-app + email message about an order. */
class OrderUpdate extends Notification
{
    public function __construct(
        public int $orderId,
        public string $kind,
        public string $title,
        public string $body,
        public string $path,
    ) {
    }

    public function via(object $notifiable): array
    {
        // The notification inside the app is always kept; e-mail only if the person has not switched it off.
        return ($notifiable->notify_email ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'path' => $this->path,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->body)
            ->action(\App\Support\PlatformBrand::in(__('Open in ArmorHub')), \App\Support\Security\GuestAccessLink::to($notifiable, $this->path));
    }
}
