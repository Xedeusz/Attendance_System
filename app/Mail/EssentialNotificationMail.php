<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EssentialNotificationMail extends Mailable
{
    private string $notificationSubject;

    /** @param array<string, string|null> $fields */
    public function __construct(
        string $subject,
        private string $name,
        private string $message,
        private string $action,
        #[\SensitiveParameter] private string $url,
        private array $fields = [],
        private ?string $note = null,
        #[\SensitiveParameter] private ?string $pdf = null,
        private ?string $filename = null,
    ) {
        $this->notificationSubject = $subject;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.essential', text: 'mail.essential-text', with: [
            'title' => $this->notificationSubject, 'recipientName' => $this->name, 'bodyMessage' => $this->message,
            'action' => $this->action, 'url' => $this->url, 'fields' => $this->fields, 'note' => $this->note,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->pdf === null ? [] : [
            Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }
}
