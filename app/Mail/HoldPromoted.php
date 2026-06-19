<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a booking owner their hold moved to first position and is theirs to confirm.
 */
class HoldPromoted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're first in line - {$this->booking->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hold-promoted',
            with: [
                'booking' => $this->booking,
                'ownerName' => $this->booking->owner->name ?? 'there',
            ],
        );
    }
}
