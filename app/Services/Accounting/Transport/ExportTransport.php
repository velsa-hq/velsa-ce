<?php

namespace App\Services\Accounting\Transport;

use App\Models\LedgerExportBatch;

/**
 * Delivery transport for a rendered export batch. Selected by
 * config('accounting.export.transport'), resolved via
 * {@see ExportTransportManager}.
 */
interface ExportTransport
{
    /**
     * Short stable identifier stored on the batch (e.g. "email").
     */
    public function name(): string;

    /**
     * Deliver the payload. Never throws - failures come back as
     * TransportResult::failed.
     */
    public function deliver(LedgerExportBatch $batch, string $payload, string $filename): TransportResult;
}
