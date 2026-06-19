<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Exhibitor;
use App\Models\InsuranceCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Digest of insurance certificates expiring within the reminder window.
 */
class CertificatesExpiringDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, InsuranceCertificate>  $certificates
     */
    public function __construct(
        public Collection $certificates,
        public int $reminderDays,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->certificates->count();

        return new Envelope(
            subject: "[Velsa] {$count} insurance certificate(s) expiring soon",
        );
    }

    public function content(): Content
    {
        $rows = $this->certificates->map(fn (InsuranceCertificate $c) => [
            'holder' => $this->holderName($c),
            'policy_type' => $c->policy_type->label(),
            'carrier' => $c->carrier,
            'expires_on' => $c->expires_on->toFormattedDateString(),
        ])->all();

        return new Content(
            markdown: 'mail.certificates-expiring',
            with: [
                'rows' => $rows,
                'reminderDays' => $this->reminderDays,
            ],
        );
    }

    private function holderName(InsuranceCertificate $certificate): string
    {
        $holder = $certificate->holder;

        return match (true) {
            $holder instanceof Client => $holder->name,
            $holder instanceof Exhibitor => $holder->company_name,
            default => 'Unknown holder',
        };
    }
}
