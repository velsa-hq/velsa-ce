<?php

namespace App\Events;

use App\Models\AuditEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an audit row is persisted. Drives account-lifecycle
 * notifications (NotifySecurityOfAccountChange).
 */
class AuditEventRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AuditEvent $event) {}
}
