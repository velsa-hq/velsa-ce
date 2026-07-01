<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookableUnit;
use App\Enums\RateCardKind;
use App\Http\Controllers\Controller;
use App\Models\EquipmentItem;
use App\Models\RateCard;
use App\Models\RateCardEntry;
use App\Models\Space;
use App\Models\Venue;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Venue-scoped, effective-dated rate cards. Entries are replaced wholesale on save.
 */
class RateCardController extends Controller
{
    public function index(): Response
    {
        $cards = RateCard::query()
            ->with('venue:id,name')
            ->withCount('entries')
            ->orderBy('venue_id')
            ->orderBy('kind')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (RateCard $c) => $this->row($c))
            ->all();

        return Inertia::render('admin/rate-cards/index', [
            'cards' => $cards,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/rate-cards/create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $card = RateCard::create($this->cardAttributes($data));
        $this->syncEntries($card, $data['entries'] ?? []);

        return to_route('admin.rate-cards.edit', $card)
            ->with('toast', ['type' => 'success', 'message' => "Rate card '{$card->name}' created."]);
    }

    public function edit(RateCard $rateCard): Response
    {
        $rateCard->load('entries');

        return Inertia::render('admin/rate-cards/edit', [
            ...$this->formOptions(),
            'card' => [
                ...$this->row($rateCard),
                'entries' => $rateCard->entries->map(fn (RateCardEntry $e) => [
                    'kind' => $e->space_id !== null ? 'space' : 'equipment',
                    'space_id' => $e->space_id,
                    'equipment_sku' => $e->equipment_sku,
                    'unit' => $e->unit->value,
                    'rate' => $e->rate_cents / 100,
                    'min_charge' => $e->min_charge_cents / 100,
                    'included_hours' => $e->included_hours,
                ])->all(),
            ],
        ]);
    }

    public function update(Request $request, RateCard $rateCard): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $rateCard->update($this->cardAttributes($data));
        $this->syncEntries($rateCard, $data['entries'] ?? []);

        return back()->with('toast', ['type' => 'success', 'message' => "Rate card '{$rateCard->name}' saved."]);
    }

    public function destroy(RateCard $rateCard): RedirectResponse
    {
        $name = $rateCard->name;
        $rateCard->delete();

        return to_route('admin.rate-cards.index')
            ->with('toast', ['type' => 'success', 'message' => "Rate card '{$name}' deleted."]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function syncEntries(RateCard $card, array $entries): void
    {
        $card->entries()->delete();

        foreach ($entries as $e) {
            $isSpace = ($e['kind'] ?? 'space') === 'space';
            $card->entries()->create([
                'space_id' => $isSpace ? ($e['space_id'] ?? null) : null,
                'equipment_sku' => $isSpace ? null : ($e['equipment_sku'] ?? null),
                'unit' => $e['unit'],
                'rate_cents' => Money::toCents($e['rate']),
                'min_charge_cents' => Money::toCents($e['min_charge'] ?? 0),
                'included_hours' => $e['included_hours'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cardAttributes(array $data): array
    {
        return [
            'venue_id' => $data['venue_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'currency' => 'USD',
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
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
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'entries' => ['array'],
            'entries.*.kind' => ['required', 'in:space,equipment'],
            'entries.*.space_id' => ['nullable', 'integer', 'exists:spaces,id'],
            'entries.*.equipment_sku' => ['nullable', 'string', 'exists:equipment_items,sku'],
            'entries.*.unit' => ['required', Rule::enum(BookableUnit::class)],
            'entries.*.rate' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'entries.*.min_charge' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'entries.*.included_hours' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RateCard $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'kind' => $c->kind->value,
            'kind_label' => $c->kind->label(),
            'currency' => $c->currency,
            'effective_from' => $c->effective_from->toDateString(),
            'effective_to' => $c->effective_to?->toDateString(),
            'is_active' => $c->is_active,
            'notes' => $c->notes,
            'venue' => $c->venue ? ['id' => $c->venue->id, 'name' => $c->venue->name] : null,
            'venue_id' => $c->venue_id,
            'entries_count' => $c->entries_count ?? $c->entries->count(),
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
