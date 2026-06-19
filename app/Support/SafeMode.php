<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/** Read + enforcement point for safe mode (Demo / Training / UAT). See config/velsa.php. */
class SafeMode
{
    public static function enabled(): bool
    {
        return (bool) config('velsa.safe_mode', false);
    }

    public static function label(): string
    {
        return (string) config('velsa.safe_mode_label', 'DEMO');
    }

    public static function mailRecipient(): ?string
    {
        $to = config('velsa.safe_mode_mail_to');

        return is_string($to) && $to !== '' ? $to : null;
    }

    /**
     * Neutralize outbound mail: redirect to the sink address, or to the log
     * channel if none set. Covers queued/scheduled mail (both use the mailer).
     */
    public static function applyMail(): void
    {
        if (! self::enabled()) {
            return;
        }

        $to = self::mailRecipient();

        if ($to !== null) {
            Mail::alwaysTo($to);

            return;
        }

        config(['mail.default' => 'log']);
    }
}
