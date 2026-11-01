<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PawCareStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
    ) {
    }

    /**
     * Mengirim notifikasi melalui database.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Menentukan data yang disimpan pada tabel notifications.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
