<?php

namespace App\Listeners;

use App\Events\AuditEventRecorded;
use App\Mail\AccountLifecycleMail;
use App\Models\User;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Emails configured recipients on account create/modify/disable/enable
 * (STIG AC-2, APSC-DV-000380/000390/000400/000430). Driven off the audit
 * trail. Off unless enabled AND recipients are set. Queues the mail so a
 * slow mailer never blocks the audited action. Removal (000410) is N/A:
 * accounts are retired by disabling, not deletion.
 */
class NotifySecurityOfAccountChange
{
    /** audit event type -> action label */
    private const LIFECYCLE = [
        'user.registered' => 'created',
        'user.profile_edited' => 'modified',
        'user.role_assigned' => 'modified',
        'user.role_unassigned' => 'modified',
        'user.disabled' => 'disabled',
        'user.enabled' => 'enabled',
    ];

    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(AuditEventRecorded $event): void
    {
        $audit = $event->event;

        $action = self::LIFECYCLE[$audit->event_type] ?? null;
        if ($action === null) {
            return;
        }

        if (! $this->settings->get('security.account_notifications_enabled', false)) {
            return;
        }

        $recipients = $this->recipients();
        if ($recipients === []) {
            return;
        }

        // affected account = audit subject for admin actions, else acting user
        // (self-service); actor = recorded user when it differs from subject
        $account = $audit->subject instanceof User ? $audit->subject : $audit->user;
        if (! $account instanceof User) {
            return;
        }

        $actor = $audit->subject instanceof User && $audit->user instanceof User ? $audit->user : null;

        Mail::to($recipients)->queue(new AccountLifecycleMail(
            action: $action,
            accountEmail: (string) $account->email,
            accountName: $account->name,
            actorEmail: $actor?->email,
            occurredAt: Carbon::parse((string) $audit->created_at)->toDayDateTimeString(),
            ip: $audit->ip,
        ));
    }

    /**
     * Parse the comma/whitespace/newline-separated recipient list.
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        $raw = (string) $this->settings->get('security.account_notification_recipients', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $e) => trim($e))
            ->filter(fn (string $e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
