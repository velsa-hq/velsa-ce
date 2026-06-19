<?php

namespace App\Mail;

use App\Models\Exhibitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Magic-link login email for the exhibitor portal.
 * The plaintext token is never stored; it lives only in this email.
 */
class ExhibitorPortalLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Exhibitor $exhibitor,
        public string $loginUrl,
        public int $expiresInDays,
    ) {}

    public function envelope(): Envelope
    {
        $eventName = $this->exhibitor->event?->name ?? 'your event';

        return new Envelope(
            subject: "Your exhibitor portal link - {$eventName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.exhibitor-portal-link',
            with: [
                'exhibitor' => $this->exhibitor,
                'eventName' => $this->exhibitor->event?->name,
                'loginUrl' => $this->loginUrl,
                'expiresInDays' => $this->expiresInDays,
            ],
        );
    }
}
