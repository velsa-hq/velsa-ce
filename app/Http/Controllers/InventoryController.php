<?php

namespace App\Http\Controllers;

use App\Models\InventoryKind;
use App\Models\ResourceInventory;
use App\Models\Venue;
use App\Models\WorkOrderItem;
use App\Services\SystemSettings\SystemSettings;
use App\Support\DateFormatter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Per-venue equipment inventory; work-order items draw against stock.
 * Retiring soft-deletes (retired_at) so historical items keep their ref.
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ResourceInventory::class);

        $lowOnly = $request->boolean('low_only');
        $kindLabels = InventoryKind::query()->pluck('label', 'key');
        $low = $this->lowStockScope();

        $lowCount = $this->scopedQuery($request)->where($low)->count();

        $resources = $this->scopedQuery($request)
            ->with('venue:id,name')
            ->when($lowOnly, fn ($q) => $q->where($low))
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (ResourceInventory $r) => [
                'id' => $r->id,
                'venue_id' => $r->venue_id,
                'venue_name' => $r->venue?->name,
                'kind' => $r->kind,
                'kind_label' => $kindLabels[$r->kind] ?? $r->kind,
                'sku' => $r->sku,
                'name' => $r->name,
                'quantity_total' => $r->quantity_total,
                'quantity_available' => $r->quantity_available,
                'reorder_point' => $r->reorder_point,
                'is_consumable' => $r->is_consumable,
                'is_low' => $r->is_consumable && $r->reorder_point > 0 && $r->quantity_available <= $r->reorder_point,
            ])->all();

        return Inertia::render('inventory/index', [
            'resources' => $resources,
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
            'kinds' => InventoryKind::query()->active()->ordered()->get(['key', 'label'])
                ->map(fn (InventoryKind $k) => ['value' => $k->key, 'label' => $k->label])
                ->all(),
            'filters' => [
                'venue_id' => $request->integer('venue_id') ?: null,
                'low_only' => $lowOnly,
                'type' => $request->string('type')->toString() ?: null,
            ],
            'low_count' => $lowCount,
        ]);
    }

    /**
     * Printable count sheet: system on-hand plus a blank physical-count
     * column. Respects the index's venue/type/low filters.
     */
    public function printSheet(Request $request, SystemSettings $settings): PdfBuilder
    {
        $this->authorize('viewAny', ResourceInventory::class);

        $kindLabels = InventoryKind::query()->pluck('label', 'key');

        $rows = $this->scopedQuery($request)
            ->with('venue:id,name')
            ->when($request->boolean('low_only'), $this->lowStockScope())
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (ResourceInventory $r) => [
                'venue' => $r->venue?->name ?? '-',
                'name' => $r->name,
                'sku' => $r->sku,
                'kind' => $kindLabels[$r->kind] ?? $r->kind,
                'on_hand' => $r->quantity_available,
                'total' => $r->quantity_total,
            ])
            ->all();

        return Pdf::view('pdf.inventory-sheet', [
            'rows' => $rows,
            'appName' => (string) config('app.name'),
            'appSubtitle' => (string) $settings->get('branding.app_subtitle', ''),
            'generatedAt' => DateFormatter::dateTime(now()),
        ])->name('inventory-count-sheet.pdf');
    }

    /**
     * Shared venue + consumable/durable type filter for the index and sheet.
     */
    private function scopedQuery(Request $request): Builder
    {
        $type = $request->string('type')->toString() ?: null;

        return ResourceInventory::query()
            ->when($request->integer('venue_id') ?: null, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($type === 'consumable', fn ($q) => $q->where('is_consumable', true))
            ->when($type === 'durable', fn ($q) => $q->where('is_consumable', false));
    }

    /**
     * Low-stock predicate: consumables at/below their reorder point.
     */
    private function lowStockScope(): callable
    {
        return fn ($q) => $q->where('is_consumable', true)
            ->where('reorder_point', '>', 0)
            ->whereColumn('quantity_available', '<=', 'reorder_point');
    }

    /**
     * Use-activity report: applied work-order item movements (deploy /
     * return / consume / replace) against inventory, newest first.
     */
    public function activity(Request $request): Response
    {
        $this->authorize('viewAny', ResourceInventory::class);

        $venueId = $request->integer('venue_id') ?: null;

        $rows = WorkOrderItem::query()
            ->whereNotNull('resource_inventory_id')
            ->whereNotNull('applied_at')
            ->with(['resource:id,name,sku,venue_id', 'workOrder:id,reference,title'])
            ->when($venueId, fn ($q, $v) => $q->whereHas('resource', fn ($qq) => $qq->where('venue_id', $v)))
            ->orderByDesc('applied_at')
            ->limit(200)
            ->get()
            ->map(fn (WorkOrderItem $i) => [
                'id' => $i->id,
                'applied_at' => DateFormatter::dateTime($i->applied_at),
                'resource_name' => $i->resource?->name,
                'resource_sku' => $i->resource?->sku,
                'action' => ucfirst((string) $i->action?->value),
                'quantity' => $i->quantity,
                'unit' => $i->unit,
                'work_order_id' => $i->work_order_id,
                'work_order_reference' => $i->workOrder?->reference,
                'work_order_title' => $i->workOrder?->title,
            ])->all();

        return Inertia::render('inventory/activity', [
            'rows' => $rows,
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
            'filters' => ['venue_id' => $venueId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ResourceInventory::class);

        $data = $this->validatePayload($request);

        ResourceInventory::query()->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "'{$data['name']}' added to inventory."]);

        return back();
    }

    public function update(Request $request, ResourceInventory $resourceInventory): RedirectResponse
    {
        $this->authorize('update', $resourceInventory);

        $resourceInventory->update($this->validatePayload($request, $resourceInventory));

        Inertia::flash('toast', ['type' => 'success', 'message' => "'{$resourceInventory->name}' updated."]);

        return back();
    }

    public function destroy(ResourceInventory $resourceInventory): RedirectResponse
    {
        $this->authorize('delete', $resourceInventory);

        // can't retire stock still out on open work orders (model enforces this too)
        $applied = WorkOrderItem::query()
            ->where('resource_inventory_id', $resourceInventory->id)
            ->whereNotNull('applied_at')
            ->exists();

        if ($applied) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "'{$resourceInventory->name}' has inventory applied to open work orders. Complete or reverse those first.",
            ]);

            return back();
        }

        $name = $resourceInventory->name;
        $resourceInventory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "'{$name}' retired."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ResourceInventory $existing = null): array
    {
        return $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'kind' => ['required', Rule::exists('inventory_kinds', 'key')->where('is_active', true)],
            'sku' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('resource_inventories', 'sku')
                    ->where(fn ($q) => $q->where('venue_id', $request->integer('venue_id')))
                    ->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'quantity_total' => ['required', 'integer', 'min:0'],
            'quantity_available' => ['required', 'integer', 'min:0', 'lte:quantity_total'],
            'is_consumable' => ['boolean'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
