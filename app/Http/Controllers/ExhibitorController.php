<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\ExhibitorOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WorkOrderStatus;
use App\Mail\ExhibitorPortalLink;
use App\Models\Booking;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\ExhibitorPayment;
use App\Models\WorkOrder;
use App\Services\Accounting\ValueFormatter;
use App\Services\Exhibitors\ExhibitorFulfillmentService;
use App\Services\MagicLinkService;
use App\Services\Payments\OrderPaymentService;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExhibitorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Exhibitor::class);

        $eventId = $request->integer('event_id') ?: null;
        $status = $request->string('status')->toString() ?: null;

        $exhibitors = Exhibitor::query()
            ->with([
                'event:id,name,portal_slug,registration_closes_at',
                'orders' => fn ($q) => $q->latest('placed_at')->limit(1),
                'orders.payments',
            ])
            ->when($eventId, fn ($q, $v) => $q->where('exhibitor_event_id', $v))
            ->when($status, fn ($q, $v) => $q->whereHas('orders', fn ($qq) => $qq->where('status', $v)))
            ->orderBy('booth_assignment')
            ->paginate(50)
            ->withQueryString();

        $rows = $exhibitors->getCollection()->map(function (Exhibitor $e) {
            $order = $e->orders->first();

            return [
                'id' => $e->id,
                'company_name' => $e->company_name,
                'contact_name' => $e->contact_name,
                'email' => $e->email,
                'booth_assignment' => $e->booth_assignment,
                'booth_size' => $e->booth_size,
                'event' => $e->event ? [
                    'id' => $e->event->id,
                    'name' => $e->event->name,
                    'portal_slug' => $e->event->portal_slug,
                ] : null,
                'order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status?->value,
                    'total_cents' => $order->total_cents,
                    'paid_cents' => $order->paid_cents,
                    'balance_cents' => max(0, $order->total_cents - $order->paid_cents),
                    'last_payment_brand' => $order->payments->first()?->card_brand,
                    'last_payment_last4' => $order->payments->first()?->last4,
                ] : null,
            ];
        });

        $events = ExhibitorEvent::query()->withCount('exhibitors')->orderBy('name')->get();
        $statusSummary = ExhibitorEvent::query()
            ->join('exhibitors', 'exhibitors.exhibitor_event_id', '=', 'exhibitor_events.id')
            ->join('exhibitor_orders', 'exhibitor_orders.exhibitor_id', '=', 'exhibitors.id')
            ->when($eventId, fn ($q, $v) => $q->where('exhibitor_events.id', $v))
            ->selectRaw('exhibitor_orders.status, count(*) as count, sum(exhibitor_orders.total_cents) as total_cents, sum(exhibitor_orders.paid_cents) as paid_cents')
            ->groupBy('exhibitor_orders.status')
            ->get()
            ->keyBy('status');

        return Inertia::render('exhibitors/index', [
            'exhibitors' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $exhibitors->currentPage(),
                    'last_page' => $exhibitors->lastPage(),
                    'total' => $exhibitors->total(),
                ],
                'links' => [
                    'prev' => $exhibitors->previousPageUrl(),
                    'next' => $exhibitors->nextPageUrl(),
                ],
            ],
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'portal_slug' => $e->portal_slug,
                'exhibitor_count' => $e->exhibitors_count,
            ]),
            'statuses' => array_map(fn (ExhibitorOrderStatus $s) => $s->value, ExhibitorOrderStatus::cases()),
            'filters' => ['event_id' => $eventId, 'status' => $status],
            'summary' => $statusSummary,
            'bookings' => $this->bookingOptions(),
        ]);
    }

    /**
     * Bookings that can host an exhibitor event; expo/trade-show sort first
     * but any non-cancelled booking is selectable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function bookingOptions(): Collection
    {
        return Booking::query()
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Inquiry->value])
            ->orderByRaw("case when kind in ('expo', 'trade_show') then 0 else 1 end")
            ->orderByDesc('start_at')
            ->limit(200)
            ->get(['id', 'reference', 'name', 'kind', 'start_at'])
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'name' => $b->name,
                'kind' => $b->kind,
                'start_at' => $b->start_at?->toIso8601String(),
            ]);
    }

    public function show(Exhibitor $exhibitor): Response
    {
        $this->authorize('view', $exhibitor);

        $exhibitor->load([
            'event:id,name,portal_slug,booking_id',
            'event.booking:id,reference,name,start_at,end_at',
            'orders.items.equipmentItem',
            'orders.payments',
        ]);

        $totals = $exhibitor->orders->reduce(function (array $acc, ExhibitorOrder $o) {
            $acc['total_cents'] += $o->total_cents;
            $acc['paid_cents'] += $o->paid_cents;
            $acc['balance_cents'] += max(0, $o->total_cents - $o->paid_cents);

            return $acc;
        }, ['total_cents' => 0, 'paid_cents' => 0, 'balance_cents' => 0]);

        // orders auto-generate per-department work orders stamped with exhibitor_id
        $workOrders = WorkOrder::query()
            ->where('exhibitor_id', $exhibitor->id)
            ->where('status', '!=', WorkOrderStatus::Cancelled->value)
            ->orderByDesc('scheduled_for')
            ->get(['id', 'reference', 'title', 'status', 'department', 'scheduled_for', 'completed_at']);

        return Inertia::render('exhibitors/show', [
            'exhibitor' => [
                'id' => $exhibitor->id,
                'company_name' => $exhibitor->company_name,
                'contact_name' => $exhibitor->contact_name,
                'email' => $exhibitor->email,
                'phone' => $exhibitor->phone,
                'booth_assignment' => $exhibitor->booth_assignment,
                'booth_size' => $exhibitor->booth_size,
                'address' => $exhibitor->address_json,
                'event' => $exhibitor->event ? [
                    'id' => $exhibitor->event->id,
                    'name' => $exhibitor->event->name,
                    'portal_slug' => $exhibitor->event->portal_slug,
                    'booking' => $exhibitor->event->booking ? [
                        'id' => $exhibitor->event->booking->id,
                        'reference' => $exhibitor->event->booking->reference,
                        'name' => $exhibitor->event->booking->name,
                        'start_at' => $exhibitor->event->booking->start_at?->toIso8601String(),
                        'end_at' => $exhibitor->event->booking->end_at?->toIso8601String(),
                    ] : null,
                ] : null,
                'orders' => $exhibitor->orders->map(fn (ExhibitorOrder $o) => $this->serializeOrder($o)),
                'totals' => $totals,
                'work_orders' => $workOrders->map(fn (WorkOrder $w) => [
                    'id' => $w->id,
                    'reference' => $w->reference,
                    'title' => $w->title,
                    'department' => $w->department,
                    'status' => $w->status?->value,
                    'scheduled_for' => $w->scheduled_for?->toIso8601String(),
                    'completed_at' => $w->completed_at?->toIso8601String(),
                ]),
                'work_order_summary' => [
                    'total' => $workOrders->count(),
                    'completed' => $workOrders->where('status', WorkOrderStatus::Completed)->count(),
                ],
            ],
            'events' => ExhibitorEvent::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExhibitorEvent $e) => ['id' => $e->id, 'name' => $e->name]),
        ]);
    }

    public function showEvent(ExhibitorEvent $event): Response
    {
        $this->authorize('viewAny', Exhibitor::class);

        $event->load([
            'booking:id,reference,name,start_at,end_at',
            'exhibitors' => fn ($q) => $q->orderBy('booth_assignment'),
            'exhibitors.orders',
        ]);

        return Inertia::render('exhibitors/event-show', [
            'bookings' => $this->bookingOptions(),
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'portal_slug' => $event->portal_slug,
                'booking_id' => $event->booking_id,
                'default_booth_size' => $event->default_booth_size,
                'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
                'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
                'advance_rate_deadline' => $event->advance_rate_deadline?->toIso8601String(),
                'late_order_surcharge_pct' => $event->late_order_surcharge_pct,
                'is_registration_open' => $event->isRegistrationOpen(),
                'booking' => $event->booking ? [
                    'id' => $event->booking->id,
                    'reference' => $event->booking->reference,
                    'name' => $event->booking->name,
                    'start_at' => $event->booking->start_at?->toIso8601String(),
                    'end_at' => $event->booking->end_at?->toIso8601String(),
                ] : null,
                'exhibitors' => $event->exhibitors->map(function (Exhibitor $e) {
                    $totalCents = $e->orders->sum('total_cents');
                    $paidCents = $e->orders->sum('paid_cents');

                    return [
                        'id' => $e->id,
                        'company_name' => $e->company_name,
                        'contact_name' => $e->contact_name,
                        'email' => $e->email,
                        'booth_assignment' => $e->booth_assignment,
                        'booth_size' => $e->booth_size,
                        'order_count' => $e->orders->count(),
                        'total_cents' => $totalCents,
                        'paid_cents' => $paidCents,
                        'balance_cents' => max(0, $totalCents - $paidCents),
                    ];
                }),
            ],
        ]);
    }

    public function showOrder(Exhibitor $exhibitor, ExhibitorOrder $order): Response
    {
        $this->authorize('view', $exhibitor);

        $order->load(['items.equipmentItem.category', 'payments']);

        $catalog = EquipmentItem::query()
            ->active()
            ->with('category:id,code,name,department')
            ->orderBy('sku')
            ->get()
            ->map(fn (EquipmentItem $i) => [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'unit_label' => $i->unit_label,
                'unit_price_cents' => $i->unit_price_cents,
                'category' => $i->category ? [
                    'code' => $i->category->code,
                    'name' => $i->category->name,
                    'department' => $i->category->department,
                ] : null,
            ]);

        return Inertia::render('exhibitors/order-show', [
            'exhibitor' => [
                'id' => $exhibitor->id,
                'company_name' => $exhibitor->company_name,
                'contact_name' => $exhibitor->contact_name,
                'email' => $exhibitor->email,
            ],
            'order' => $this->serializeOrder($order, withItems: true),
            'catalog' => $catalog,
        ]);
    }

    public function addOrderItem(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order, ExhibitorFulfillmentService $fulfillment): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        $data = $request->validate([
            'equipment_item_id' => ['required', 'integer', 'exists:equipment_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $item = EquipmentItem::query()->findOrFail($data['equipment_item_id']);

        if ($fulfillment->departmentLocked($order, $item->category?->department)) {
            return $this->departmentLockedResponse($item->category?->department);
        }

        DB::transaction(function () use ($order, $item, $data) {
            ExhibitorOrderItem::fromCatalog($order, $item, (int) $data['quantity']);
            $order->recalculateTotals();
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Added {$data['quantity']} x {$item->name} to order.",
        ]);
    }

    public function removeOrderItem(Exhibitor $exhibitor, ExhibitorOrder $order, ExhibitorOrderItem $item, ExhibitorFulfillmentService $fulfillment): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        if ($fulfillment->departmentLocked($order, $item->department)) {
            return $this->departmentLockedResponse($item->department);
        }

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $order->recalculateTotals();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Item removed.']);
    }

    public function updateOrderItem(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order, ExhibitorOrderItem $item, ExhibitorFulfillmentService $fulfillment): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        abort_if((bool) $order->status?->blocksEdit(), 403);

        if ($fulfillment->departmentLocked($order, $item->department)) {
            return $this->departmentLockedResponse($item->department);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        DB::transaction(function () use ($order, $item, $data) {
            $item->update(['quantity' => (int) $data['quantity']]);
            $order->recalculateTotals();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Quantity updated.']);
    }

    private function departmentLockedResponse(?string $department): RedirectResponse
    {
        $label = $department ? ucfirst($department) : 'this';

        return back()->with('toast', [
            'type' => 'error',
            'message' => "The {$label} setup work order is already complete - create a manual work order for further changes.",
        ]);
    }

    /**
     * Admin status override, cancel/reopen only; paid states are driven by
     * the payment machinery, never set by hand.
     */
    public function setOrderStatus(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,cancelled'],
        ]);
        $target = ExhibitorOrderStatus::from($data['status']);

        if ($target === ExhibitorOrderStatus::Cancelled && $order->paid_cents > 0) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Refund the recorded payments before cancelling this order.',
            ]);
        }

        $order->update(['status' => $target->value]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $target === ExhibitorOrderStatus::Cancelled ? 'Order cancelled.' : 'Order reopened.',
        ]);
    }

    public function capturePayment(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order, OrderPaymentService $payments): RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('payments.process'), 403);

        $data = $request->validate([
            'card_token' => ['required', 'string', 'max:120'],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        $payment = $payments->charge($order, $data['card_token'], $data['amount_cents'] ?? null);

        if ($payment->status === PaymentStatus::Captured) {
            return back()->with('toast', [
                'type' => 'success',
                'message' => 'Captured '.ValueFormatter::usd($payment->amount_cents).' on the card.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'error',
            'message' => 'Card declined: '.($payment->failure_reason ?? 'unknown reason').'.',
        ]);
    }

    public function recordManualPayment(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order, OrderPaymentService $payments): RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('payments.process'), 403);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:check,wire,cash,ach'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $payments->recordManual(
                $order,
                (int) $data['amount_cents'],
                $data['method'],
                $data['reference'] ?? null,
                $data['note'] ?? null,
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Recorded '.ValueFormatter::usd($payment->amount_cents)." ({$data['method']}).",
        ]);
    }

    public function refundPayment(Request $request, Exhibitor $exhibitor, ExhibitorOrder $order, ExhibitorPayment $payment, OrderPaymentService $payments): RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('payments.refund'), 403);

        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payments->refund($payment, (int) $data['amount_cents'], $data['reason'] ?? null, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Refunded '.ValueFormatter::usd((int) $data['amount_cents']).'.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Exhibitor::class);

        $data = $this->validateExhibitor($request);

        $exhibitor = Exhibitor::query()->create($data);

        return redirect()
            ->route('exhibitors.show', $exhibitor)
            ->with('toast', ['type' => 'success', 'message' => "Added {$exhibitor->company_name}."]);
    }

    public function update(Request $request, Exhibitor $exhibitor): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        $exhibitor->update($this->validateExhibitor($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Exhibitor updated.']);
    }

    public function destroy(Exhibitor $exhibitor): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        $hasPayments = $exhibitor->orders()
            ->where('paid_cents', '>', 0)
            ->exists();

        if ($hasPayments) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete an exhibitor with recorded payments. Refund and void the orders first.',
            ]);
        }

        $name = $exhibitor->company_name;
        $exhibitor->delete();

        return redirect()
            ->route('exhibitors.index')
            ->with('toast', ['type' => 'success', 'message' => "Deleted {$name}."]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateExhibitor(Request $request): array
    {
        return $request->validate([
            'exhibitor_event_id' => ['required', 'integer', 'exists:exhibitor_events,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'booth_assignment' => ['nullable', 'string', 'max:60'],
            'booth_size' => ['nullable', 'string', 'max:40'],
        ]);
    }

    /**
     * Issue a magic-link login token, email it, and flash the login URL so
     * staff can copy/paste if delivery is delayed.
     */
    public function issuePortalLink(Exhibitor $exhibitor, MagicLinkService $service, SystemSettings $settings): RedirectResponse
    {
        $this->authorize('manage', $exhibitor);

        // same configurable TTL as the self-service path
        $ttlDays = (int) $settings->get('security.portal_magic_link_ttl_days', MagicLinkService::DEFAULT_TTL_DAYS);
        $token = $service->issue($exhibitor, $ttlDays);
        $absoluteUrl = URL::to($service->loginUrl($token));

        if (! empty($exhibitor->email)) {
            Mail::to($exhibitor->email)->send(new ExhibitorPortalLink(
                $exhibitor,
                $absoluteUrl,
                $ttlDays,
            ));
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => empty($exhibitor->email)
                ? "Portal link (no email on file): {$absoluteUrl}"
                : "Portal link emailed to {$exhibitor->email}. (Copy: {$absoluteUrl})",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeOrder(ExhibitorOrder $order, bool $withItems = false): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'subtotal_cents' => $order->subtotal_cents,
            'tax_cents' => $order->tax_cents,
            'total_cents' => $order->total_cents,
            'paid_cents' => $order->paid_cents,
            'balance_cents' => max(0, $order->total_cents - $order->paid_cents),
            'placed_at' => $order->placed_at?->toIso8601String(),
            'item_count' => $order->items->count(),
            'payment_count' => $order->payments->count(),
        ];

        if ($withItems) {
            $payload['items'] = $order->items->map(fn (ExhibitorOrderItem $i) => [
                'id' => $i->id,
                'sku' => $i->sku,
                'name' => $i->name,
                'department' => $i->department,
                'gl_account' => $i->gl_account,
                'quantity' => $i->quantity,
                'unit_price_cents' => $i->unit_price_cents,
                'line_total_cents' => $i->line_total_cents,
                'equipment_item_id' => $i->equipment_item_id,
            ]);
            $payload['payments'] = $order->payments->map(fn (ExhibitorPayment $p) => [
                'id' => $p->id,
                'provider' => $p->provider,
                'status' => $p->status?->value,
                'amount_cents' => $p->amount_cents,
                'refunded_amount_cents' => $p->refunded_amount_cents,
                'refundable_cents' => $p->refundableAmountCents(),
                'card_brand' => $p->card_brand,
                'last4' => $p->last4,
                'processed_at' => $p->processed_at?->toIso8601String(),
                'refunded_at' => $p->refunded_at?->toIso8601String(),
            ]);
        }

        return $payload;
    }
}
