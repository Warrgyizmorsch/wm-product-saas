<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public ?User $user = null,
        public int $expiresInMinutes = 10
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Password Reset Verification Code - :app', ['app' => config('app.name', 'ERP Portal')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password-otp',
            with: [
                'otp' => $this->otp,
                'userName' => $this->user?->name ?? '',
                'expiresInMinutes' => $this->expiresInMinutes,
            ]
        );
    }
}

