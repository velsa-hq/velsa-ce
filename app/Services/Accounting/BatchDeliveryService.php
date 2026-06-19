<?php

namespace App\Services\Accounting;

use App\Models\LedgerExportBatch;
use App\Services\Accounting\Transport\ExportTransportManager;
use App\Services\AuditLogger;

/**
 * Renders an export batch, hands it to the configured transport, and
 * records the outcome on the batch plus an audit entry.
 */
class BatchDeliveryService
{
    public function __construct(
        protected LedgerExporter $exporter,
        protected ExportTransportManager $transports,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Deliver or re-deliver a batch; no-ops unless ready/sent/failed.
     */
    public function deliver(LedgerExportBatch $batch): LedgerExportBatch
    {
        if (! in_array($batch->status, ['ready', 'sent', 'failed'], true)) {
            return $batch;
        }

        $transport = $this->transports->resolve();

        if ($transport === null) {
            // no automated transport configured - manual download / hand-off
            $batch->update([
                'delivery_transport' => 'none',
                'delivery_detail' => 'Manual download / hand-off',
            ]);

            return $batch->fresh();
        }

        $payload = $this->exporter->renderPayload($batch);
        $filename = "ledger-{$batch->period}-{$batch->id}.csv";
        $result = $transport->deliver($batch, $payload, $filename);

        $batch->update($result->delivered ? [
            'status' => 'sent',
            'sent_at' => now(),
            'delivery_transport' => $transport->name(),
            'delivery_detail' => $result->detail,
            'failure_reason' => null,
        ] : [
            'status' => 'failed',
            'delivery_transport' => $transport->name(),
            'failure_reason' => $result->error,
        ]);

        $this->auditLogger->record(
            eventType: 'ledger.batch_delivery',
            subject: $batch->fresh(),
            payload: [
                'period' => $batch->period,
                'transport' => $transport->name(),
                'delivered' => $result->delivered,
                'detail' => $result->delivered ? $result->detail : $result->error,
            ],
        );

        return $batch->fresh();
    }
}
