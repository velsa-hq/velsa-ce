<?php

namespace App\Events;

use App\Models\ExhibitorPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by OrderPaymentService after a successful charge. One listener per side effect.
 */
class PaymentCaptured
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ExhibitorPayment $payment) {}
}
