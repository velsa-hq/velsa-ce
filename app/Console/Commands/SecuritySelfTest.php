<?php

namespace App\Console\Commands;

use App\Services\Security\SecuritySelfTest as SelfTest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Verify security functions are operating (NIST SI-6, STIG APSC-DV-002760/002770).
 * Exits non-zero on any FAIL; WARN does not fail the run.
 */
#[Signature('security:self-test')]
#[Description('Verify that the application security functions are operating (SI-6)')]
class SecuritySelfTest extends Command
{
    public function handle(SelfTest $selfTest): int
    {
        $rows = [];
        $failed = false;

        foreach ($selfTest->run() as $check) {
            $failed = $failed || $check['status'] === SelfTest::FAIL;
            $rows[] = [
                match ($check['status']) {
                    SelfTest::PASS => '<fg=green>PASS</>',
                    SelfTest::WARN => '<fg=yellow>WARN</>',
                    default => '<fg=red>FAIL</>',
                },
                $check['label'],
                $check['detail'],
            ];
        }

        $this->table(['Status', 'Check', 'Detail'], $rows);

        if ($failed) {
            $this->error('Security self-test FAILED - one or more security functions are not operating.');

            return self::FAILURE;
        }

        $this->info('Security self-test passed.');

        return self::SUCCESS;
    }
}
