<?php

namespace App\Http\Controllers;

use App\Enums\BookingNarrativeKind;
use App\Enums\BookingStatus;
use App\Enums\ClientType;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Requests\BookingUpdateRequest;
use App\Models\Booking;
use App\Models\BookingNarrative;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EventKind;
use App\Models\EventOutline;
use App\Models\Lead;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Models\Venue;
use App\Services\Accounting\ValueFormatter;
use App\Services\Bookings\BookingWriter;
use App\Services\BookingSettlement;
use App\Services\SystemSettings\SystemSettings;
use App\Support\DateFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Booking::class);

        $venueId = $request->integer('venue_id') ?: null;
        $status = $request->string('status')->toString() ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $bookings = Booking::query()
            ->with([
                'venue:id,name,slug',
                'client:id,name',
                'owner:id,name,email',
                'spaces:id,booking_id,space_id,start_at,end_at',
                'spaces.space:id,name,kind',
            ])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($from, fn ($q, $d) => $q->whereDate('start_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('start_at', '<=', $d))
            ->orderBy('start_at')
            ->paginate(50)
            ->withQueryString();

        $rows = $bookings->getCollection()->map(fn (Booking $b) => [
            'id' => $b->id,
            'reference' => $b->reference,
            'name' => $b->name,
            'kind' => $b->kind,
            'status' => $b->status?->value,
            'start_at' => $b->start_at?->toIso8601String(),
            'end_at' => $b->end_at?->toIso8601String(),
            'total_cents' => $b->total_cents,
            'attendance_estimate' => $b->attendance_estimate,
            'venue' => $b->venue ? ['id' => $b->venue->id, 'name' => $b->venue->name, 'slug' => $b->venue->slug] : null,
            'client_name' => $b->client?->name,
            'owner_email' => $b->owner?->email,
            'spaces' => $b->spaces->map(fn ($bs) => [
                'name' => $bs->space?->name,
                'kind' => $bs->space?->kind,
            ])->all(),
        ]);

        $summary = [
            'total' => $bookings->total(),
            'by_status' => Booking::query()
                ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
                ->selectRaw('status, count(*) as count, sum(total_cents) as total_cents')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
        ];

        return Inertia::render('bookings/index', [
            'bookings' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ],
                'links' => [
                    'prev' => $bookings->previousPageUrl(),
                    'next' => $bookings->nextPageUrl(),
                ],
            ],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'statuses' => array_map(fn (BookingStatus $s) => $s->value, BookingStatus::cases()),
            'filters' => [
                'venue_id' => $venueId,
                'status' => $status,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'summary' => $summary,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Booking::class);

        $venues = Venue::query()
            ->active()
            ->with(['spaces:id,venue_id,parent_space_id,name,kind,capacity'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        // Optional prefill when arriving from a venue / space detail page.
        $prefillVenueId = $request->integer('venue_id') ?: null;
        $prefillSpaceId = $request->integer('space_id') ?: null;
        $prefill = $prefillVenueId !== null
            ? ['venue_id' => $prefillVenueId, 'space_id' => $prefillSpaceId]
            : null;

        $fromLead = null;
        $fromLeadId = $request->integer('from_lead') ?: null;

        if ($fromLeadId !== null) {
            $lead = Lead::query()->find($fromLeadId);
            if ($lead !== null && $lead->converted_booking_id === null) {
                $fromLead = [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'client_id' => $lead->client_id,
                    'venue_id' => $lead->venue_id,
                    'estimated_value_cents' => $lead->estimated_value_cents,
                    'expected_close_date' => $lead->expected_close_date?->toDateString(),
                ];
            }
        }

        return Inertia::render('bookings/create', [
            'venues' => $venues->map(fn (Venue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'slug' => $v->slug,
                'spaces' => $v->spaces->map(fn ($s) => [
                    'id' => $s->id,
                    'parent_space_id' => $s->parent_space_id,
                    'name' => $s->name,
                    'kind' => $s->kind,
                    'capacity' => $s->capacity,
                ])->all(),
            ])->values(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])->values(),
            'client_types' => array_map(fn (ClientType $t) => $t->value, ClientType::cases()),
            'kinds' => EventKind::query()->active()->ordered()->get(['key', 'label'])
                ->map(fn (EventKind $k) => ['value' => $k->key, 'label' => $k->label])
                ->all(),
            'creatable_statuses' => array_map(
                fn (BookingStatus $s) => $s->value,
                [BookingStatus::Inquiry, BookingStatus::Hold, BookingStatus::Tentative, BookingStatus::Definite],
            ),
            'from_lead' => $fromLead,
            'prefill' => $prefill,
        ]);
    }

    public function store(BookingStoreRequest $request, BookingWriter $writer): RedirectResponse
    {
        $data = $request->validated();

        try {
            $booking = $writer->create($data, $request->user());
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['spaces' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Booking {$booking->reference} created."]);

        return to_route('bookings.show', $booking);
    }

    /**
     * Copy a booking into a fresh inquiry. Space placements are not copied:
     * the source still occupies its slots and the DB forbids overlaps, so
     * spaces are re-added once the new dates are chosen.
     */
    public function clone(Booking $booking): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $clone = $booking->replicate([
            'reference', 'cancelled_at', 'cancel_reason', 'hold_rank', 'hold_expires_at',
        ]);
        $clone->name = $booking->name.' (Copy)';
        $clone->status = BookingStatus::Inquiry;
        $clone->lead_id = null;
        $clone->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cloned to {$clone->reference} - set the dates and spaces."]);

        return to_route('bookings.edit', $clone);
    }

    public function show(Booking $booking): Response
    {
        $this->authorize('view', $booking);

        $booking->load([
            'venue:id,name,slug',
            'client:id,name,type',
            'owner:id,name,email',
            'spaces.space:id,name,kind,capacity,venue_id',
            'invoices',
            'paymentSchedule.installments.invoice:id,number',
            'staffAssignments.user:id,name,email',
        ]);

        $narratives = $booking->narratives()
            ->with('author:id,name')
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->get();

        $contracts = Contract::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'status', 'total_cents', 'sent_at', 'signed_at']);

        $outline = EventOutline::query()
            ->where('booking_id', $booking->id)
            ->withCount('items')
            ->first(['id', 'booking_id', 'published_version', 'published_at']);

        return Inertia::render('bookings/show', [
            'booking' => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'name' => $booking->name,
                'kind' => $booking->kind,
                'status' => $booking->status?->value,
                'start_at' => $booking->start_at?->toIso8601String(),
                'end_at' => $booking->end_at?->toIso8601String(),
                'total_cents' => $booking->total_cents,
                'attendance_estimate' => $booking->attendance_estimate,
                'notes' => $booking->notes,
                'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                'cancel_reason' => $booking->cancel_reason,
                'venue' => $booking->venue ? [
                    'id' => $booking->venue->id,
                    'name' => $booking->venue->name,
                    'slug' => $booking->venue->slug,
                ] : null,
                'client' => $booking->client ? [
                    'id' => $booking->client->id,
                    'name' => $booking->client->name,
                    'type' => $booking->client->type?->value,
                ] : null,
                'owner' => $booking->owner ? [
                    'id' => $booking->owner->id,
                    'name' => $booking->owner->name,
                    'email' => $booking->owner->email,
                ] : null,
                'spaces' => $booking->spaces->map(fn (BookingSpace $bs) => [
                    'id' => $bs->id,
                    'name' => $bs->space?->name,
                    'kind' => $bs->space?->kind,
                    'capacity' => $bs->space?->capacity,
                    'start_at' => $bs->start_at?->toIso8601String(),
                    'end_at' => $bs->end_at?->toIso8601String(),
                ])->all(),
            ],
            'contracts' => $contracts,
            'outline' => $outline ? [
                'id' => $outline->id,
                'published_version' => $outline->published_version,
                'published_at' => $outline->published_at?->toIso8601String(),
                'items_count' => $outline->items_count,
            ] : null,
            'billing' => [
                'deposit_percent' => (float) $booking->deposit_percent,
                'invoiced_cents' => $booking->invoicedCents(),
                'remaining_to_invoice_cents' => $booking->remainingToInvoiceCents(),
                'invoices' => $booking->invoices
                    ->sortByDesc('id')
                    ->values()
                    ->map(fn ($i) => [
                        'id' => $i->id,
                        'number' => $i->number,
                        'kind' => $i->notes,
                        'status' => $i->status?->value,
                        'status_label' => $i->status?->label(),
                        'total_cents' => $i->total_cents,
                        'paid_cents' => $i->paid_cents,
                        'balance_cents' => $i->balanceCents(),
                        'issued_on' => $i->issued_on?->toDateString(),
                        'due_on' => $i->due_on?->toDateString(),
                    ]),
                'payment_schedule' => $booking->paymentSchedule ? [
                    'id' => $booking->paymentSchedule->id,
                    'total_cents' => $booking->paymentSchedule->total_cents,
                    'installments' => $booking->paymentSchedule->installments
                        ->map(fn ($i) => [
                            'id' => $i->id,
                            'sequence' => $i->sequence,
                            'due_date' => $i->due_date?->toDateString(),
                            'amount_cents' => $i->amount_cents,
                            'label' => $i->label,
                            'invoice_id' => $i->invoice_id,
                            'invoice_number' => $i->invoice?->number,
                            'invoiced_at' => $i->invoiced_at?->toIso8601String(),
                            'paid_at' => $i->paid_at?->toIso8601String(),
                        ])
                        ->all(),
                ] : null,
            ],
            'narratives' => $narratives->map(fn (BookingNarrative $n) => [
                'id' => $n->id,
                'kind' => $n->kind?->value,
                'kind_label' => $n->kind?->label(),
                'body' => $n->body,
                'happened_at' => $n->happened_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
                'author' => $n->author ? [
                    'id' => $n->author->id,
                    'name' => $n->author->name,
                ] : null,
            ])->all(),
            'narrative_kinds' => collect(BookingNarrativeKind::cases())
                ->map(fn (BookingNarrativeKind $k) => [
                    'value' => $k->value,
                    'label' => $k->label(),
                ])
                ->all(),
            'staff' => $booking->staffAssignments->map(fn (StaffAssignment $a) => [
                'id' => $a->id,
                'role' => $a->role,
                'start_at' => $a->start_at?->toIso8601String(),
                'end_at' => $a->end_at?->toIso8601String(),
                'hourly_rate_cents' => $a->hourly_rate_cents,
                'duration_hours' => $a->durationHours(),
                'labor_cost_cents' => $a->laborCostCents(),
                'notes' => $a->notes,
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'email' => $a->user->email,
                ] : null,
            ])->all(),
            'staff_candidates' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->all(),
            'documents' => $booking->documentsForDisplay(),
        ]);
    }

    /**
     * Post-event settlement PDF: rolls up charges, invoices, payments, and
     * refunds into a single reconciliation artifact for finance.
     */
    public function downloadSettlement(
        Booking $booking,
        BookingSettlement $settlement,
        SystemSettings $settings,
    ): PdfBuilder {
        $this->authorize('view', $booking);

        $data = $settlement->forBooking($booking);

        return Pdf::view('pdf.booking-settlement', [
            'booking' => $data['booking'],
            'charges' => $data['charges'],
            'charges_subtotal_cents' => $data['charges_subtotal_cents'],
            'invoices' => $data['invoices'],
            'payments' => $data['payments'],
            'totals' => $data['totals'],
            'appName' => (string) config('app.name'),
            'appSubtitle' => (string) $settings->get('branding.app_subtitle', ''),
        ])->name("settlement-{$booking->reference}.pdf");
    }

    public function edit(Booking $booking): Response
    {
        $this->authorize('update', $booking);

        $booking->load(['spaces:id,booking_id,space_id']);

        $venues = Venue::query()
            ->active()
            ->with(['spaces:id,venue_id,parent_space_id,name,kind,capacity'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('bookings/edit', [
            'booking' => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'venue_id' => $booking->venue_id,
                'client_id' => $booking->client_id,
                'name' => $booking->name,
                'kind' => $booking->kind,
                'status' => $booking->status?->value,
                'start_at' => DateFormatter::editDateTime($booking->start_at),
                'end_at' => DateFormatter::editDateTime($booking->end_at),
                'total_dollars' => $booking->total_cents > 0 ? ValueFormatter::apply($booking->total_cents, 'money:dot') : null,
                'attendance_estimate' => $booking->attendance_estimate,
                'notes' => $booking->notes,
                'cancel_reason' => $booking->cancel_reason,
                'spaces' => $booking->spaces->pluck('space_id')->all(),
            ],
            'venues' => $venues->map(fn (Venue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'slug' => $v->slug,
                'spaces' => $v->spaces->map(fn ($s) => [
                    'id' => $s->id,
                    'parent_space_id' => $s->parent_space_id,
                    'name' => $s->name,
                    'kind' => $s->kind,
                    'capacity' => $s->capacity,
                ])->all(),
            ])->values(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])->values(),
            'kinds' => EventKind::query()->active()->ordered()->get(['key', 'label'])
                ->map(fn (EventKind $k) => ['value' => $k->key, 'label' => $k->label])
                ->all(),
            'statuses' => array_map(fn (BookingStatus $s) => $s->value, BookingStatus::cases()),
        ]);
    }

    public function update(BookingUpdateRequest $request, Booking $booking, BookingWriter $writer): RedirectResponse
    {
        $data = $request->validated();

        try {
            $writer->update($booking, $data);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['spaces' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Booking {$booking->reference} updated."]);

        return to_route('bookings.show', $booking);
    }

    /**
     * Append an entry to the booking narrative. Append-only by convention:
     * staff add a correction entry rather than rewriting history.
     */
    public function storeNarrative(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $kinds = array_map(fn (BookingNarrativeKind $k) => $k->value, BookingNarrativeKind::cases());

        $data = $request->validate([
            'kind' => ['required', Rule::in($kinds)],
            'body' => ['required', 'string', 'max:5000'],
            'happened_at' => ['nullable', 'date'],
        ]);

        $booking->narratives()->create([
            'author_user_id' => $request->user()?->id,
            'kind' => $data['kind'],
            'body' => $data['body'],
            'happened_at' => $data['happened_at'] ?? now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Narrative entry added.']);

        return to_route('bookings.show', $booking);
    }
}
