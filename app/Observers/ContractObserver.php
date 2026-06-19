<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Contract;
use App\Services\AutoNarrative;

class ContractObserver
{
    public function __construct(protected AutoNarrative $autoNarrative) {}

    // log a booking narrative on externally-visible contract status changes
    public function updated(Contract $contract): void
    {
        if (! $contract->wasChanged('status')) {
            return;
        }

        $current = $contract->status?->value;
        if ($current === null) {
            return;
        }

        $verb = match ($current) {
            'sent' => 'sent for signature',
            'viewed' => 'opened by client',
            'partially_signed' => 'partially signed',
            'signed' => 'fully signed',
            'declined' => 'declined',
            'expired' => 'expired',
            default => null,
        };

        if ($verb === null) {
            return;
        }

        $booking = $contract->booking;
        if (! $booking instanceof Booking) {
            return;
        }

        $this->autoNarrative->append(
            $booking,
            "Contract {$contract->reference} {$verb}.",
        );
    }
}
