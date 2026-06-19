<?php

namespace App\Console\Commands;

use App\Enums\InsuranceCertificateStatus;
use App\Mail\CertificatesExpiringDigest;
use App\Models\InsuranceCertificate;
use App\Services\SystemSettings\SystemSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Auto-expire lapsed COI certificates and email an expiring-soon digest.
 * The expire sweep always runs; the digest only when reminders are enabled
 * and recipients are configured.
 */
#[Signature('compliance:check-certificates')]
#[Description('Auto-expire lapsed insurance certificates and email an expiring-soon digest')]
class CheckInsuranceCertificates extends Command
{
    public function handle(SystemSettings $settings): int
    {
        $today = CarbonImmutable::now()->startOfDay();

        $expired = InsuranceCertificate::query()
            ->where('status', InsuranceCertificateStatus::Approved)
            ->whereDate('expires_on', '<', $today->toDateString())
            ->update([
                'status' => InsuranceCertificateStatus::Expired,
                'updated_at' => now(),
            ]);

        $this->info("Marked {$expired} certificate(s) expired.");

        $reminderDays = (int) $settings->get('compliance.expiry_reminder_days', 30);
        $windowEnd = $today->addDays($reminderDays);

        $expiring = InsuranceCertificate::query()
            ->with('holder')
            ->expiringBy($windowEnd)
            ->whereDate('expires_on', '>=', $today->toDateString())
            ->orderBy('expires_on')
            ->get();

        $this->info("{$expiring->count()} certificate(s) expiring within {$reminderDays} day(s).");

        if (! $settings->get('compliance.expiry_reminders_enabled', false)) {
            return self::SUCCESS;
        }

        $recipients = $this->recipients($settings);
        if ($recipients === [] || $expiring->isEmpty()) {
            return self::SUCCESS;
        }

        Mail::to($recipients)->queue(new CertificatesExpiringDigest($expiring, $reminderDays));
        $this->info('Expiry digest queued to '.count($recipients).' recipient(s).');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function recipients(SystemSettings $settings): array
    {
        $raw = (string) $settings->get('compliance.notification_recipients', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $e) => trim($e))
            ->filter(fn (string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
