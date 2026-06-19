<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional notice sent when a refund posts against an invoice.
 */
class InvoiceRefunded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public int $amountCents,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Refund issued - invoice {$this->invoice->number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-refunded',
            with: [
                'invoice' => $this->invoice,
                'amountCents' => $this->amountCents,
                'reason' => $this->reason,
                'recipientName' => $this->recipientName(),
            ],
        );
    }

    protected function recipientName(): string
    {
        $invoiceable = $this->invoice->invoiceable;
        if ($invoiceable === null) {
            return 'there';
        }

        if ($invoiceable instanceof ExhibitorOrder && $invoiceable->exhibitor) {
            return $invoiceable->exhibitor->contact_name ?: 'there';
        }

        if ($invoiceable instanceof Booking) {
            $primary = $invoiceable->client?->contacts()
                ->where('is_primary', true)
                ->first();
            if ($primary !== null) {
                return $primary->name ?: 'there';
            }

            return $invoiceable->client?->name ?: 'there';
        }

        return 'there';
    }
}
