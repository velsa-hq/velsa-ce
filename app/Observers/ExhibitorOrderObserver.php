<?php

namespace App\Observers;

use App\Models\ExhibitorOrder;
use App\Services\Exhibitors\ExhibitorFulfillmentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExhibitorOrderObserver
{
    public function __construct(private ExhibitorFulfillmentService $fulfillment) {}

    // sync is idempotent and never writes back to the order, so no recursion.
    // fulfillment is a side effect: never let a failure block the order/payment
    public function saved(ExhibitorOrder $order): void
    {
        try {
            $this->fulfillment->syncForOrder($order);
        } catch (Throwable $e) {
            Log::error('Exhibitor fulfillment sync failed', [
                'exhibitor_order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
