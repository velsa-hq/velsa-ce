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
 * "Your invoice is ready" email sent when InvoiceService issues a
 * booking invoice (deposit, balance, installment).
 */
class IssuedInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->number} - ".config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.issued-invoice',
            with: [
                'invoice' => $this->invoice,
                'recipientName' => $this->recipientName(),
                'eventName' => $this->eventName(),
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

    protected function eventName(): ?string
    {
        $invoiceable = $this->invoice->invoiceable;

        if ($invoiceable instanceof Booking) {
            return $invoiceable->name;
        }

        if ($invoiceable instanceof ExhibitorOrder) {
            return $invoiceable->exhibitor?->booking?->name;
        }

        return null;
    }
}
