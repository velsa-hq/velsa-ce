<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');
        $exhibitor->load([
            'event:id,name,portal_slug,registration_closes_at,booking_id',
            'event.booking:id,reference,name,start_at,end_at,venue_id',
            'event.booking.venue:id,name,slug',
            'orders.items',
            'orders.payments',
        ]);

        $totals = $exhibitor->orders->reduce(function (array $acc, ExhibitorOrder $o) {
            $acc['total_cents'] += $o->total_cents;
            $acc['paid_cents'] += $o->paid_cents;
            $acc['balance_cents'] += max(0, $o->total_cents - $o->paid_cents);

            return $acc;
        }, ['total_cents' => 0, 'paid_cents' => 0, 'balance_cents' => 0]);

        $current = $exhibitor->currentDraftOrder();

        return Inertia::render('portal/dashboard', [
            'exhibitor' => [
                'id' => $exhibitor->id,
                'company_name' => $exhibitor->company_name,
                'contact_name' => $exhibitor->contact_name,
                'email' => $exhibitor->email,
                'phone' => $exhibitor->phone,
                'booth_assignment' => $exhibitor->booth_assignment,
                'booth_size' => $exhibitor->booth_size,
            ],
            'event' => $exhibitor->event ? [
                'name' => $exhibitor->event->name,
                'registration_closes_at' => $exhibitor->event->registration_closes_at?->toIso8601String(),
                'is_registration_open' => $exhibitor->event->isRegistrationOpen(),
                'booking' => $exhibitor->event->booking ? [
                    'reference' => $exhibitor->event->booking->reference,
                    'name' => $exhibitor->event->booking->name,
                    'start_at' => $exhibitor->event->booking->start_at?->toIso8601String(),
                    'end_at' => $exhibitor->event->booking->end_at?->toIso8601String(),
                    'venue_name' => $exhibitor->event->booking->venue?->name,
                ] : null,
            ] : null,
            'current_order' => $current ? [
                'id' => $current->id,
                'order_number' => $current->order_number,
                'status' => $current->status?->value,
                'item_count' => $current->items->count(),
                'total_cents' => $current->total_cents,
                'balance_cents' => max(0, $current->total_cents - $current->paid_cents),
            ] : null,
            'order_history' => $exhibitor->orders->map(fn (ExhibitorOrder $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status?->value,
                'total_cents' => $o->total_cents,
                'paid_cents' => $o->paid_cents,
                'balance_cents' => max(0, $o->total_cents - $o->paid_cents),
                'item_count' => $o->items->count(),
                'placed_at' => $o->placed_at?->toIso8601String(),
            ])->sortByDesc('id')->values(),
            'totals' => $totals,
        ]);
    }
}
