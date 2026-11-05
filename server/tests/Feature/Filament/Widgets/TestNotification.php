<?php

namespace Tests\Feature\Filament\Widgets;

use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    public function __construct(private string $title) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => null, 'format' => 'filament'];
    }
}
