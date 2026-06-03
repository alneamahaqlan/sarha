<?php

namespace App\Mail;

use App\Models\Clinic;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a clinic when its registration is approved. Carries the freshly
 * issued login credentials (phone + password) and a link to the sign-in page.
 */
class ClinicApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Clinic $clinic,
        public string $password,
        public ?string $loginUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.clinic_approved_email_subject'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clinic-approved',
            with: [
                'clinic'   => $this->clinic,
                'phone'    => $this->clinic->phone,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl ? url($this->loginUrl) : url('/app/login'),
            ],
        );
    }
}
