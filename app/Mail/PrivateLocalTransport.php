<?php

namespace App\Mail;

use App\Models\LocalMailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class PrivateLocalTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Private local mail capture is disabled outside local development.');
        }
        $email = $message->getOriginalMessage();
        LocalMailMessage::create(['recipient_hash' => hash('sha256', $message->getEnvelope()->getRecipients()[0]->getAddress()), 'payload' => [
            'to' => array_map(fn ($address) => $address->getAddress(), $message->getEnvelope()->getRecipients()),
            'subject' => $email->getSubject(), 'text' => $email->getTextBody() ?? strip_tags($email->getHtmlBody() ?? ''),
        ], 'expires_at' => now()->addHours(24)]);
    }

    public function __toString(): string
    {
        return 'private_local';
    }
}
