<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recompute and compare the integrity HMAC on audit_events to detect out-of-band
 * tampering with the audit trail (STIG APSC-DV-001350 / NIST AU-9). Exits non-zero
 * if any row's stored hash no longer matches its content.
 */
#[Signature('audit:verify-integrity {--since= : Only check rows created on/after this date} {--until= : Only check rows created on/before this date}')]
#[Description('Verify the cryptographic integrity of the audit trail (AU-9)')]
class VerifyAuditIntegrity extends Command
{
    public function handle(): int
    {
        $query = AuditEvent::query()->orderBy('id');

        if ($since = $this->option('since')) {
            $query->where('created_at', '>=', $since);
        }

        if ($until = $this->option('until')) {
            $query->where('created_at', '<=', $until);
        }

        $checked = 0;
        $unsealed = 0;
        $mismatches = [];

        $query->chunkById(500, function ($events) use (&$checked, &$unsealed, &$mismatches): void {
            foreach ($events as $event) {
                $checked++;

                if ($event->integrity_hash === null) {
                    $unsealed++;

                    continue;
                }

                if (! $event->hasValidIntegrityHash()) {
                    $mismatches[] = $event->getKey();
                }
            }
        });

        $this->info("Checked {$checked} audit row(s); {$unsealed} unsealed (pre-dating integrity hashing).");

        if ($mismatches !== []) {
            $this->error('Audit integrity FAILURE - hash mismatch on row id(s): '.implode(', ', $mismatches));

            return self::FAILURE;
        }

        $this->info('Audit integrity OK - no hash mismatches.');

        return self::SUCCESS;
    }
}
