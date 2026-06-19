<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentSchedule;
use App\Services\Accounting\PaymentScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * N-installment payment schedules on a Booking, for events that need a
 * custom timeline. The booking-level deposit/balance flow stays available
 * for simpler events; the two paths are mutually exclusive.
 */
class PaymentScheduleController extends Controller
{
    /**
     * Create or replace a booking's schedule. Invoiced installments stay
     * frozen; see PaymentScheduleService::replaceInstallments for diff rules.
     */
    public function replace(
        Booking $booking,
        Request $request,
        PaymentScheduleService $service,
    ): RedirectResponse {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.sequence' => ['required', 'integer', 'min:1', 'max:99'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount_cents' => ['required', 'integer', 'min:1'],
            'installments.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        if ((float) $booking->deposit_percent > 0) {
            return back()->withErrors([
                'installments' => 'This booking is on the deposit/balance flow. Set deposit percent to 0 on the booking before creating a payment schedule.',
            ]);
        }

        try {
            $service->replaceInstallments(
                $booking,
                $data['installments'],
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['installments' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment schedule saved.');
    }

    public function destroy(
        PaymentSchedule $paymentSchedule,
        Request $request,
        PaymentScheduleService $service,
    ): RedirectResponse {
        $this->authorize('update', $paymentSchedule->booking);

        try {
            $service->delete($paymentSchedule, $request->user()?->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['schedule' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment schedule removed.');
    }
}
