<?php

namespace App\Services\Accounting\Transport;

use App\Mail\LedgerBatchMail;
use App\Models\LedgerExportBatch;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;

/**
 * Emails the rendered batch file to the configured GL recipient.
 */
class EmailExportTransport implements ExportTransport
{
    public function __construct(protected Mailer $mailer) {}

    public function name(): string
    {
        return 'email';
    }

    public function deliver(LedgerExportBatch $batch, string $payload, string $filename): TransportResult
    {
        $recipient = config('accounting.export.email.recipient');

        if (empty($recipient)) {
            return TransportResult::failed(
                'No GL recipient configured (accounting.export.email.recipient).',
            );
        }

        try {
            $this->mailer->to($recipient)->send(
                new LedgerBatchMail($batch, $payload, $filename),
            );
        } catch (Throwable $e) {
            return TransportResult::failed($e->getMessage());
        }

        return TransportResult::delivered("Emailed to {$recipient}");
    }
}
