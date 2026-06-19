<?php

namespace App\Mail;

use App\Models\LedgerExportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a rendered journal export batch to the GL recipient, file attached.
 */
class LedgerBatchMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LedgerExportBatch $batch,
        public string $payload,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Journal export {$this->batch->period} - {$this->batch->entry_count} entries",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ledger-batch',
            with: ['batch' => $this->batch],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->payload, $this->filename)
                ->withMime('text/csv'),
        ];
    }
}
