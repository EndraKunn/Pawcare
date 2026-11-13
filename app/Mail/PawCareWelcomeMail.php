<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PawCareWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $recipientName, public readonly string $accountRole) {}

    public function build(): self
    {
        return $this->subject('Selamat Datang di PawCare')
            ->view('elga-auth::emails.welcome', ['name' => $this->recipientName, 'role' => $this->accountRole]);
    }
}
