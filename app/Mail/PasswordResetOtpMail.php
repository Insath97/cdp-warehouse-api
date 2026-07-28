<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $otpCode;
    public int $expiresInMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userName, string $otpCode, int $expiresInMinutes = 10)
    {
        $this->userName = $userName;
        $this->otpCode = $otpCode;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name') . ' - Password Reset Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.password-reset-otp',
            with: [
                'userName' => $this->userName,
                'otpCode' => $this->otpCode,
                'expiresInMinutes' => $this->expiresInMinutes,
                'appName' => config('app.name', 'CDP Warehouse'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
