<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A short in-app notice with a link. */
class SimpleNotice extends Notification
{
    public function __construct(private string $title, private string $body, private string $path)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => 'notice', 'title' => $this->title, 'body' => $this->body, 'path' => $this->path];
    }
}
