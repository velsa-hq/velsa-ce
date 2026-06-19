<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $categories = EquipmentCategory::query()
            ->active()
            ->with(['items' => fn ($q) => $q->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $current = $exhibitor->currentDraftOrder();

        $event = ExhibitorEvent::find($exhibitor->exhibitor_event_id);
        $surchargePct = $event !== null ? $event->late_order_surcharge_pct : 0;

        return Inertia::render('portal/catalog', [
            'categories' => $categories->map(fn (EquipmentCategory $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'description' => $c->description,
                'department' => $c->department,
                'items' => $c->items->map(fn (EquipmentItem $i) => [
                    'id' => $i->id,
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'description' => $i->description,
                    'unit_label' => $i->unit_label,
                    'unit_price_cents' => $i->unit_price_cents,
                    // post-deadline price when an advance rate is set
                    'late_price_cents' => $surchargePct > 0
                        ? (int) round($i->unit_price_cents * (1 + $surchargePct / 100))
                        : null,
                    // price if ordered now
                    'effective_price_cents' => $event?->pricedNowCents($i->unit_price_cents)
                        ?? $i->unit_price_cents,
                ])->all(),
            ]),
            'pricing' => [
                'advance_rate_deadline' => $event?->advance_rate_deadline?->toIso8601String(),
                'late_order_surcharge_pct' => $surchargePct,
                'late_rate_active' => (bool) $event?->lateRateActive(),
            ],
            'current_order' => $current ? [
                'id' => $current->id,
                'order_number' => $current->order_number,
                'item_count' => $current->items->count(),
            ] : null,
        ]);
    }
}
