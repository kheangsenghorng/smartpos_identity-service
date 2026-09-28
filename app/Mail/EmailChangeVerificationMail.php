<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $pendingEmail,
        public string $verificationUrl,
        public int $expiresInMinutes = 15
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SmartPOS — Confirm Your New Email Address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email-change',
            text: 'emails.verify-email-change-text',
            with: [
                'user' => $this->user,
                'pendingEmail' => $this->pendingEmail,
                'verificationUrl' => $this->verificationUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
