<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Account-lifecycle change notice to SAs/ISSOs.
 * STIG AC-2(1)/(4) - APSC-DV-000380/000390/000400/000430.
 */
class AccountLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $action,
        public string $accountEmail,
        public ?string $accountName,
        public ?string $actorEmail,
        public string $occurredAt,
        public ?string $ip,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Velsa] Account {$this->action}: {$this->accountEmail}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-lifecycle',
            with: [
                'action' => $this->action,
                'accountEmail' => $this->accountEmail,
                'accountName' => $this->accountName,
                'actorEmail' => $this->actorEmail,
                'occurredAt' => $this->occurredAt,
                'ip' => $this->ip,
            ],
        );
    }
}
