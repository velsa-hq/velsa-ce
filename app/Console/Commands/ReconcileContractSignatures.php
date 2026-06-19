<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\Signing\ContractDispatcher;
use App\Services\Signing\SignatureProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Backstop for missed/late Connect webhooks: poll the signature
 * provider for every in-flight contract and reconcile any drift
 * (e.g. remote completed but local still Sent). On a newly-detected
 * completion it also captures the executed PDF.
 *
 * Scheduled in routes/console.php. A no-op against the Fake provider /
 * when DocuSign is disabled (statuses come back "unknown").
 */
#[Signature('contracts:reconcile-signatures')]
#[Description('Reconcile in-flight contract statuses against the signature provider')]
class ReconcileContractSignatures extends Command
{
    public function handle(SignatureProvider $provider, ContractDispatcher $dispatcher): int
    {
        $reconciled = 0;

        Contract::query()
            ->whereIn('status', [
                ContractStatus::Sent->value,
                ContractStatus::Viewed->value,
                ContractStatus::PartiallySigned->value,
            ])
            ->whereNotNull('provider_envelope_id')
            ->chunkById(100, function ($batch) use ($provider, $dispatcher, &$reconciled): void {
                foreach ($batch as $contract) {
                    try {
                        $remote = $provider->getEnvelopeStatus((string) $contract->provider_envelope_id);
                    } catch (\Throwable $e) {
                        $this->warn("envelope {$contract->provider_envelope_id}: {$e->getMessage()}");

                        continue;
                    }

                    if ($this->reconcile($contract, $remote, $dispatcher)) {
                        $reconciled++;
                    }
                }
            });

        $this->info("Reconciled {$reconciled} contract(s) from the provider.");

        return self::SUCCESS;
    }

    private function reconcile(Contract $contract, string $remoteStatus, ContractDispatcher $dispatcher): bool
    {
        if ($remoteStatus === 'completed' && $contract->status !== ContractStatus::Signed) {
            $contract->update(['status' => ContractStatus::Signed->value, 'signed_at' => now()]);
            try {
                $dispatcher->storeSignedDocument($contract->fresh());
            } catch (\Throwable $e) {
                $this->warn("signed-pdf fetch failed for {$contract->reference}: {$e->getMessage()}");
            }

            return true;
        }

        if ($remoteStatus === 'declined' && $contract->status !== ContractStatus::Declined) {
            $contract->update(['status' => ContractStatus::Declined->value, 'declined_at' => now()]);

            return true;
        }

        if ($remoteStatus === 'voided' && $contract->status !== ContractStatus::Voided) {
            $contract->update(['status' => ContractStatus::Voided->value, 'voided_at' => now()]);

            return true;
        }

        if ($remoteStatus === 'delivered' && $contract->status === ContractStatus::Sent) {
            $contract->update(['status' => ContractStatus::Viewed->value, 'viewed_at' => now()]);

            return true;
        }

        return false;
    }
}
