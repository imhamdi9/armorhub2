<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One notification type for everything that happens in an installation job; the words are chosen by the service that sends it. */
class InstallationUpdate extends Notification
{
    /** @param array<string, string> $replace values for ":name" placeholders in the title and the body (both are translated for the person who receives it) */
    public function __construct(public string $title, public string $body, public string $path, public array $replace = [])
    {
    }

    public function via(object $notifiable): array
    {
        // The notification inside the app is always kept; e-mail only if the person has not switched it off.
        return ($notifiable->notify_email ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? ($notifiable->preferredLocale() ?? 'en') : 'en';

        return ['kind' => 'installation', 'title' => \Illuminate\Support\Facades\Lang::get($this->title, $this->replace, $locale), 'body' => \Illuminate\Support\Facades\Lang::get($this->body, $this->replace, $locale), 'path' => $this->path];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The same words as in the app, with their placeholders filled in (never the raw ":text" / "#:id").
        $locale = method_exists($notifiable, 'preferredLocale') ? ($notifiable->preferredLocale() ?? 'en') : 'en';

        return (new MailMessage)->subject(\Illuminate\Support\Facades\Lang::get($this->title, $this->replace, $locale))->greeting("Hello {$notifiable->name},")->line(\Illuminate\Support\Facades\Lang::get($this->body, $this->replace, $locale))
            ->action('Open', rtrim(config('platform.web_url'), '/').$this->path);
    }
}
