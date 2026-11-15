<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Throwable;

class PawCareStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60];

    public function __construct(public string $title, public string $message, public ?string $url = null)
    {
        $this->onQueue('notifications')->afterCommit();
    }

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url];
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('PawCare notification permanently failed.', [
            'title' => $this->title, 'exception' => $exception->getMessage(),
        ]);
    }
}
