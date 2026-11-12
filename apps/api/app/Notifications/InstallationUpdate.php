<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One notification type for everything that happens in an installation job; the words are chosen by the service that sends it. */
class InstallationUpdate extends Notification
{
    public function __construct(public string $title, public string $body, public string $path)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => 'installation', 'title' => $this->title, 'body' => $this->body, 'path' => $this->path];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting("Hello {$notifiable->name},")->line($this->body)
            ->action('Open', rtrim(config('platform.web_url'), '/').$this->path);
    }
}
