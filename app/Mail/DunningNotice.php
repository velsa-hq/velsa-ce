<?php

namespace App\Mail;

use App\Enums\DunningStage;
use App\Models\Booking;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Past-due reminder email; subject and tone escalate with the dunning stage.
 */
class DunningNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public DunningStage $stage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->stage) {
                DunningStage::FirstNotice => "Friendly reminder - invoice {$this->invoice->number}",
                DunningStage::SecondNotice => "Past due - invoice {$this->invoice->number}",
                DunningStage::FinalNotice => "Final notice - invoice {$this->invoice->number}",
                DunningStage::Collections => "URGENT - invoice {$this->invoice->number} referred to collections",
                default => "Invoice reminder - {$this->invoice->number}",
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.dunning-notice',
            with: [
                'invoice' => $this->invoice,
                'stage' => $this->stage,
                'stageLabel' => $this->stage->label(),
                'daysPastDue' => $this->invoice->daysPastDue(),
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
