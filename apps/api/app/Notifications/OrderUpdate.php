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
        return ['database', 'mail'];
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
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body)
            ->action('Open in ArmorHub', rtrim(config('platform.web_url'), '/').$this->path);
    }
}
