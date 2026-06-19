<?php

namespace App\Http\Controllers;

use App\Enums\InventoryAction;
use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\ResourceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\SystemSettings\SystemSettings;
use App\Support\DateFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class WorkOrderController extends Controller
{
    /** @return list<array{value: string, label: string}> */
    private function kindOptions(): array
    {
        return array_map(
            fn (WorkOrderKind $k) => [
                'value' => $k->value,
                'label' => ucwords(str_replace('_', ' ', $k->value)),
            ],
            WorkOrderKind::cases(),
        );
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrder::class);

        $venueId = $request->integer('venue_id') ?: null;
        $status = $request->string('status')->toString() ?: null;

        $workOrders = WorkOrder::query()
            ->with(['venue:id,name,slug', 'assignee:id,name,email', 'template:id,name'])
            ->withCount('items')
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('scheduled_for')
            ->paginate(50)
            ->withQueryString();

        $rows = $workOrders->getCollection()->map(fn (WorkOrder $wo) => [
            'id' => $wo->id,
            'reference' => $wo->reference,
            'title' => $wo->title,
            'kind' => $wo->kind?->value,
            'status' => $wo->status?->value,
            'priority' => $wo->priority,
            'scheduled_for' => $wo->scheduled_for?->toIso8601String(),
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'cost_cents' => $wo->cost_cents,
            'venue_name' => $wo->venue?->name,
            'assignee_email' => $wo->assignee?->email,
            'template_name' => $wo->template?->name,
            'item_count' => $wo->items_count,
            'is_overdue' => $wo->isOverdue(),
            'is_recurring' => $wo->template_id !== null,
        ]);

        // active statuses count all-time; terminal ones are windowed so the chips don't grow unbounded
        $summaryWindowDays = 90;
        $terminal = [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value];

        $summary = WorkOrder::query()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->where(fn ($q) => $q
                ->whereNotIn('status', $terminal)
                ->orWhere(fn ($qq) => $qq
                    ->whereIn('status', $terminal)
                    ->where('updated_at', '>=', now()->subDays($summaryWindowDays))))
            ->selectRaw('status, count(*) as count, sum(cost_cents) as cost_cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $overdueCount = WorkOrder::query()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->overdue()
            ->count();

        return Inertia::render('work-orders/index', [
            'work_orders' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $workOrders->currentPage(),
                    'last_page' => $workOrders->lastPage(),
                    'total' => $workOrders->total(),
                ],
                'links' => [
                    'prev' => $workOrders->previousPageUrl(),
                    'next' => $workOrders->nextPageUrl(),
                ],
            ],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'statuses' => array_map(fn (WorkOrderStatus $s) => $s->value, WorkOrderStatus::cases()),
            'kinds' => $this->kindOptions(),
            'assignees' => User::query()->orderBy('name')->get(['id', 'name', 'email'])->all(),
            'filters' => ['venue_id' => $venueId, 'status' => $status],
            'summary' => $summary,
            'summary_window_days' => $summaryWindowDays,
            'overdue_count' => $overdueCount,
        ]);
    }

    public function show(WorkOrder $workOrder): Response
    {
        $this->authorize('view', $workOrder);

        $workOrder->load([
            'venue:id,slug,name',
            'assignee:id,name,email',
            'requester:id,name,email',
            'template:id,name',
            'booking:id,reference,name',
            'exhibitor:id,company_name,booth_assignment',
            'exhibitorOrder:id,order_number',
            'items',
        ]);

        return Inertia::render('work-orders/show', [
            'work_order' => [
                'id' => $workOrder->id,
                'reference' => $workOrder->reference,
                'title' => $workOrder->title,
                'description' => $workOrder->description,
                'kind' => $workOrder->kind?->value,
                'status' => $workOrder->status?->value,
                'priority' => $workOrder->priority,
                'scheduled_for' => $workOrder->scheduled_for?->toIso8601String(),
                'completed_at' => $workOrder->completed_at?->toIso8601String(),
                'cost_cents' => $workOrder->cost_cents,
                'is_overdue' => $workOrder->isOverdue(),
                'venue' => $workOrder->venue ? [
                    'id' => $workOrder->venue->id,
                    'slug' => $workOrder->venue->slug,
                    'name' => $workOrder->venue->name,
                ] : null,
                'assigned_to_user_id' => $workOrder->assigned_to_user_id,
                'assignee' => $workOrder->assignee ? [
                    'name' => $workOrder->assignee->name,
                    'email' => $workOrder->assignee->email,
                ] : null,
                'requester' => $workOrder->requester ? [
                    'name' => $workOrder->requester->name,
                    'email' => $workOrder->requester->email,
                ] : null,
                'template' => $workOrder->template ? ['name' => $workOrder->template->name] : null,
                'department' => $workOrder->department,
                'booking' => $workOrder->booking ? [
                    'id' => $workOrder->booking->id,
                    'reference' => $workOrder->booking->reference,
                    'name' => $workOrder->booking->name,
                ] : null,
                'exhibitor_source' => $workOrder->exhibitor && $workOrder->exhibitorOrder ? [
                    'exhibitor_id' => $workOrder->exhibitor->id,
                    'company_name' => $workOrder->exhibitor->company_name,
                    'booth_assignment' => $workOrder->exhibitor->booth_assignment,
                    'order_id' => $workOrder->exhibitorOrder->id,
                    'order_number' => $workOrder->exhibitorOrder->order_number,
                ] : null,
                'items' => $workOrder->items->map(fn (WorkOrderItem $i) => [
                    'id' => $i->id,
                    'resource_inventory_id' => $i->resource_inventory_id,
                    'name' => $i->name,
                    'sku' => $i->sku,
                    'quantity' => $i->quantity,
                    'unit' => $i->unit,
                    'unit_cost_cents' => $i->unit_cost_cents,
                    'action' => $i->action?->value,
                    'notes' => $i->notes,
                    'applied_at' => $i->applied_at?->toIso8601String(),
                ])->all(),
            ],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'kinds' => $this->kindOptions(),
            'assignees' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'item_actions' => collect(InventoryAction::cases())
                ->map(fn (InventoryAction $a) => ['value' => $a->value, 'label' => ucfirst($a->value)])
                ->all(),
            'resources' => ResourceInventory::query()
                ->where('venue_id', $workOrder->venue_id)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'quantity_available'])
                ->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('work-orders/create', [
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'selected_venue_id' => $request->integer('venue_id') ?: null,
            'kinds' => $this->kindOptions(),
            'assignees' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WorkOrder::class);

        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', Rule::enum(WorkOrderKind::class)],
            'priority' => ['required', 'integer', 'min:1', 'max:5'],
            'scheduled_for' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $workOrder = WorkOrder::query()->create([
            ...$data,
            'status' => ! empty($data['assigned_to_user_id'])
                ? WorkOrderStatus::Assigned
                : WorkOrderStatus::Open,
            'requested_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Work order {$workOrder->reference} created."]);

        return to_route('work-orders.show', $workOrder);
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['required', Rule::enum(WorkOrderKind::class)],
            'priority' => ['required', 'integer', 'min:1', 'max:5'],
            'scheduled_for' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $workOrder->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Work order {$workOrder->reference} updated."]);

        return back();
    }

    /**
     * Move a work order through its lifecycle; the model's saving hook
     * stamps/clears completed_at as the status crosses Completed.
     */
    public function updateStatus(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('complete', $workOrder);

        $data = $request->validate([
            'status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $previous = $workOrder->status;
        $target = WorkOrderStatus::from($data['status']);

        // status change + inventory side effect must commit atomically
        DB::transaction(function () use ($workOrder, $target, $previous, $data) {
            $workOrder->status = $target;
            if (array_key_exists('cost_cents', $data) && $data['cost_cents'] !== null) {
                $workOrder->cost_cents = $data['cost_cents'];
            }
            $workOrder->save();

            // apply deltas crossing into Completed, reverse them crossing back out
            if ($target === WorkOrderStatus::Completed && $previous !== WorkOrderStatus::Completed) {
                $workOrder->applyInventoryDeltas();
            } elseif ($previous === WorkOrderStatus::Completed && $target !== WorkOrderStatus::Completed) {
                $workOrder->reverseInventoryDeltas();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Work order {$workOrder->reference} marked {$workOrder->status->value}."]);

        return back();
    }

    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('delete', $workOrder);

        // return applied stock before the order and its items vanish
        $workOrder->reverseInventoryDeltas();

        $reference = $workOrder->reference;
        $workOrder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Work order {$reference} deleted."]);

        return to_route('work-orders.index');
    }

    public function printOne(WorkOrder $workOrder, SystemSettings $settings): PdfBuilder
    {
        $this->authorize('view', $workOrder);

        return $this->renderPdf(
            new Collection([$workOrder]),
            "work-order-{$workOrder->reference}",
            $settings,
        );
    }

    /**
     * Print a group of work orders in one PDF; honors the same venue/status
     * filters as the index.
     */
    public function printGroup(Request $request, SystemSettings $settings): PdfBuilder
    {
        $this->authorize('viewAny', WorkOrder::class);

        $ids = array_values(array_filter(array_map(
            'intval',
            explode(',', $request->string('ids')->toString()),
        )));
        $venueId = $request->integer('venue_id') ?: null;
        $status = $request->string('status')->toString() ?: null;

        $orders = WorkOrder::query()
            ->when(
                $ids !== [],
                fn ($q) => $q->whereIn('id', $ids),
                fn ($q) => $q
                    ->when($venueId, fn ($qq, $v) => $qq->where('venue_id', $v))
                    ->when($status, fn ($qq, $s) => $qq->where('status', $s)),
            )
            ->orderBy('scheduled_for')
            ->get();

        return $this->renderPdf($orders, 'work-orders', $settings);
    }

    /**
     * @param  Collection<int, WorkOrder>  $orders
     */
    private function renderPdf(Collection $orders, string $name, SystemSettings $settings): PdfBuilder
    {
        $orders->loadMissing([
            'venue:id,name',
            'assignee:id,name',
            'requester:id,name',
            'items',
        ]);

        return Pdf::view('pdf.work-orders', [
            'orders' => $orders->map(fn (WorkOrder $wo) => $this->pdfPayload($wo))->all(),
            'appName' => (string) config('app.name'),
            'appSubtitle' => (string) $settings->get('branding.app_subtitle', ''),
        ])->name("{$name}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfPayload(WorkOrder $wo): array
    {
        $subtotal = $wo->items->sum(fn (WorkOrderItem $i) => (int) $i->unit_cost_cents * (int) $i->quantity);

        return [
            'reference' => $wo->reference,
            'title' => $wo->title,
            'status' => ucfirst(str_replace('_', ' ', (string) $wo->status?->value)),
            'kind' => ucfirst(str_replace('_', ' ', (string) $wo->kind?->value)),
            'priority' => $wo->priority,
            'scheduled_for' => DateFormatter::dateTimeWithDay($wo->scheduled_for) ?? '-',
            'completed_at' => DateFormatter::dateTime($wo->completed_at),
            'venue' => $wo->venue?->name,
            'assignee' => $wo->assignee?->name,
            'requester' => $wo->requester?->name,
            'cost' => $wo->cost_cents !== null ? '$'.number_format($wo->cost_cents / 100, 2) : null,
            'description' => $wo->description,
            'items' => $wo->items->map(fn (WorkOrderItem $i) => [
                'name' => $i->name,
                'sku' => $i->sku,
                'quantity' => $i->quantity,
                'unit' => $i->unit,
                'action' => ucfirst(str_replace('_', ' ', (string) $i->action?->value)),
                'line' => $i->unit_cost_cents !== null
                    ? '$'.number_format(($i->unit_cost_cents * $i->quantity) / 100, 2)
                    : null,
            ])->all(),
            'items_subtotal' => $subtotal > 0 ? '$'.number_format($subtotal / 100, 2) : null,
        ];
    }

    public function storeItem(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        $workOrder->items()->create($this->validateItem($request, $workOrder));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added.']);

        return back();
    }

    public function updateItem(Request $request, WorkOrderItem $workOrderItem): RedirectResponse
    {
        $this->authorize('update', $workOrderItem->workOrder);

        $data = $this->validateItem($request, $workOrderItem->workOrder);

        DB::transaction(function () use ($workOrderItem, $data) {
            // if the delta is already applied, reverse-edit-reapply so stock tracks the new values
            $wasApplied = $workOrderItem->applied_at !== null;

            if ($wasApplied) {
                $workOrderItem->reverseFromInventory();
            }

            $workOrderItem->update($data);

            if ($wasApplied) {
                $workOrderItem->refresh()->applyToInventory();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item updated.']);

        return back();
    }

    public function destroyItem(WorkOrderItem $workOrderItem): RedirectResponse
    {
        $this->authorize('update', $workOrderItem->workOrder);

        DB::transaction(function () use ($workOrderItem) {
            // return stock this item deployed before it disappears
            $workOrderItem->reverseFromInventory();
            $workOrderItem->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Item removed.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request, WorkOrder $workOrder): array
    {
        return $request->validate([
            'resource_inventory_id' => [
                'nullable',
                'integer',
                Rule::exists('resource_inventories', 'id')->where('venue_id', $workOrder->venue_id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'action' => ['required', Rule::enum(InventoryAction::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
