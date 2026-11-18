<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/** A short in-app notice with a link. The title is a translatable sentence (":name" style placeholders); the body is shown as given. */
class SimpleNotice extends Notification
{
    /** @param array<string, string> $replace values for the placeholders in the title */
    public function __construct(private string $title, private string $body, private string $path, private array $replace = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? ($notifiable->preferredLocale() ?? 'en') : 'en';

        return ['kind' => 'notice', 'title' => Lang::get($this->title, $this->replace, $locale), 'body' => $this->replace ? Lang::get($this->body, $this->replace, $locale) : $this->body, 'path' => $this->path];
    }
}
