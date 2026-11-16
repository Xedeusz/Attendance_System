<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountInvitationMail extends Mailable
{
    public function __construct(private string $activationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activate your LIMCOMA account');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.activation', with: ['link' => $this->activationUrl]);
    }
}
