<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email mirror of a high/urgent in-app notification. Sent alongside the
 * PlatformNotification by NotificationService::push() for critical events only.
 */
class CriticalAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertTitle,
        public string $alertBody,
        public ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alertTitle);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.critical-alert',
            with: [
                'alertTitle' => $this->alertTitle,
                'alertBody'  => $this->alertBody,
                // Normalise relative panel URLs (e.g. /app/admin/...) to absolute.
                'actionUrl'  => $this->actionUrl ? url($this->actionUrl) : null,
            ],
        );
    }
}
