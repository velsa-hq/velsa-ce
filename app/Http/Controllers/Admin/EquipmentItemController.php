<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for the exhibitor equipment/services catalog.
 */
class EquipmentItemController extends Controller
{
    public function index(): Response
    {
        // raw join rather than a model map to keep static analysis type-clean
        $items = DB::table('equipment_items')
            ->leftJoin('equipment_categories', 'equipment_categories.id', '=', 'equipment_items.equipment_category_id')
            ->orderBy('equipment_items.name')
            ->get([
                'equipment_items.id',
                'equipment_items.sku',
                'equipment_items.name',
                'equipment_items.unit_label',
                'equipment_items.unit_price_cents',
                'equipment_items.advance_price_cents',
                'equipment_items.is_active',
                'equipment_categories.name as category',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'sku' => $r->sku,
                'name' => $r->name,
                'category' => $r->category,
                'unit_label' => $r->unit_label,
                'unit_price_cents' => (int) $r->unit_price_cents,
                'advance_price_cents' => $r->advance_price_cents !== null ? (int) $r->advance_price_cents : null,
                'is_active' => (bool) $r->is_active,
            ]);

        return Inertia::render('admin/equipment-items/index', [
            'items' => $items,
            'categories' => EquipmentCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        EquipmentItem::query()->create($this->modelFields($data));

        return back()->with('toast', ['type' => 'success', 'message' => 'Catalog item added.']);
    }

    public function update(Request $request, EquipmentItem $equipmentItem): RedirectResponse
    {
        $data = $this->validatePayload($request, $equipmentItem);

        $equipmentItem->update($this->modelFields($data));

        return back()->with('toast', ['type' => 'success', 'message' => 'Catalog item updated.']);
    }

    public function toggle(EquipmentItem $equipmentItem): RedirectResponse
    {
        $equipmentItem->update(['is_active' => ! $equipmentItem->is_active]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Catalog item updated.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?EquipmentItem $item = null): array
    {
        return $request->validate([
            'equipment_category_id' => ['required', 'integer', 'exists:equipment_categories,id'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('equipment_items', 'sku')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit_label' => ['required', 'string', 'max:40'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'advance_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function modelFields(array $data): array
    {
        return [
            'equipment_category_id' => $data['equipment_category_id'],
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'unit_label' => $data['unit_label'],
            'unit_price_cents' => Money::toCents($data['unit_price']),
            'advance_price_cents' => isset($data['advance_price'])
                ? Money::toCents($data['advance_price'])
                : null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
