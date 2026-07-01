<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookableUnit;
use App\Enums\RateCardKind;
use App\Http\Controllers\Controller;
use App\Models\EquipmentItem;
use App\Models\RatePackage;
use App\Models\Space;
use App\Models\Venue;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Venue-scoped, effective-dated package bundles sold at a single price.
 * Items are replaced wholesale on save.
 */
class RatePackageController extends Controller
{
    public function index(): Response
    {
        $packages = RatePackage::query()
            ->with('venue:id,name')
            ->withCount('items')
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (RatePackage $p) => $this->row($p))
            ->all();

        return Inertia::render('admin/rate-packages/index', [
            'packages' => $packages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/rate-packages/create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $package = RatePackage::create($this->packageAttributes($data));
        $this->syncItems($package, $data['items'] ?? []);

        return to_route('admin.rate-packages.edit', $package)
            ->with('toast', ['type' => 'success', 'message' => "Package '{$package->name}' created."]);
    }

    public function edit(RatePackage $ratePackage): Response
    {
        $ratePackage->load('items');

        return Inertia::render('admin/rate-packages/edit', [
            ...$this->formOptions(),
            'package' => [
                ...$this->row($ratePackage),
                'items' => $ratePackage->items->map(fn ($i) => [
                    'kind' => $i->space_id !== null ? 'space' : ($i->equipment_sku !== null ? 'equipment' : 'service'),
                    'space_id' => $i->space_id,
                    'equipment_sku' => $i->equipment_sku,
                    'label' => $i->label,
                    'quantity' => $i->quantity,
                    'unit' => $i->unit?->value,
                    'notes' => $i->notes,
                ])->all(),
            ],
        ]);
    }

    public function update(Request $request, RatePackage $ratePackage): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $ratePackage->update($this->packageAttributes($data));
        $this->syncItems($ratePackage, $data['items'] ?? []);

        return back()->with('toast', ['type' => 'success', 'message' => "Package '{$ratePackage->name}' saved."]);
    }

    public function destroy(RatePackage $ratePackage): RedirectResponse
    {
        $name = $ratePackage->name;
        $ratePackage->delete();

        return to_route('admin.rate-packages.index')
            ->with('toast', ['type' => 'success', 'message' => "Package '{$name}' deleted."]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(RatePackage $package, array $items): void
    {
        $package->items()->delete();

        foreach ($items as $i) {
            $kind = $i['kind'] ?? 'service';
            $package->items()->create([
                'space_id' => $kind === 'space' ? ($i['space_id'] ?? null) : null,
                'equipment_sku' => $kind === 'equipment' ? ($i['equipment_sku'] ?? null) : null,
                'label' => $i['label'] ?? null,
                'quantity' => (int) ($i['quantity'] ?? 1),
                'unit' => $i['unit'] ?? null,
                'notes' => $i['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function packageAttributes(array $data): array
    {
        return [
            'venue_id' => $data['venue_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'currency' => 'USD',
            'price_cents' => Money::toCents($data['price']),
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(RateCardKind::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['array'],
            'items.*.kind' => ['required', 'in:space,equipment,service'],
            'items.*.space_id' => ['nullable', 'integer', 'exists:spaces,id'],
            'items.*.equipment_sku' => ['nullable', 'string', 'exists:equipment_items,sku'],
            'items.*.label' => ['nullable', 'string', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit' => ['nullable', Rule::enum(BookableUnit::class)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RatePackage $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'kind' => $p->kind->value,
            'kind_label' => $p->kind->label(),
            'currency' => $p->currency,
            'price_cents' => $p->price_cents,
            'price' => $p->price_cents / 100,
            'effective_from' => $p->effective_from->toDateString(),
            'effective_to' => $p->effective_to?->toDateString(),
            'is_active' => $p->is_active,
            'description' => $p->description,
            'venue' => $p->venue ? ['id' => $p->venue->id, 'name' => $p->venue->name] : null,
            'venue_id' => $p->venue_id,
            'items_count' => $p->items_count ?? $p->items->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Venue $v) => ['value' => $v->id, 'label' => $v->name])->all(),
            'spaces' => Space::query()->orderBy('name')->get(['id', 'name', 'venue_id'])
                ->map(fn (Space $s) => ['value' => $s->id, 'label' => $s->name, 'venue_id' => $s->venue_id])->all(),
            'equipment' => EquipmentItem::query()->orderBy('name')->get(['sku', 'name'])
                ->map(fn (EquipmentItem $e) => ['value' => $e->sku, 'label' => $e->name])->all(),
            'kinds' => array_map(fn (RateCardKind $k) => ['value' => $k->value, 'label' => $k->label()], RateCardKind::cases()),
            'units' => array_map(fn (BookableUnit $u) => ['value' => $u->value, 'label' => $u->label()], BookableUnit::cases()),
        ];
    }
}
