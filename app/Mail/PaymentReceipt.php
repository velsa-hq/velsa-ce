<?php

namespace App\Mail;

use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional receipt sent on every successful payment capture.
 */
class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ExhibitorPayment $payment,
        public ExhibitorOrder $order,
        public ?Invoice $invoice = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment received - order {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        $exhibitor = $this->order->exhibitor;

        return new Content(
            markdown: 'mail.payment-receipt',
            with: [
                'payment' => $this->payment,
                'order' => $this->order,
                'invoice' => $this->invoice,
                'exhibitor' => $exhibitor,
                'recipientName' => $exhibitor?->contact_name ?: 'there',
            ],
        );
    }
}
