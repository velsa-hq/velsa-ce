<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryAction;
use App\Enums\WorkOrderKind;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\WorkOrderTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for recurring work-order templates (preventive maintenance).
 * The daily workorders:materialize command turns active templates into
 * work orders on their cadence. RRULE is composed from / parsed back into
 * structured fields so users never type raw iCal.
 */
class WorkOrderTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $venueId = $request->integer('venue_id') ?: null;

        $templates = WorkOrderTemplate::query()
            ->with('venue:id,name')
            ->withCount('workOrders')
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (WorkOrderTemplate $t) => [
                'id' => $t->id,
                'venue_id' => $t->venue_id,
                'venue_name' => $t->venue?->name,
                'name' => $t->name,
                'kind' => $t->kind?->value,
                'lookahead_days' => $t->lookahead_days,
                'default_assignee_role' => $t->default_assignee_role,
                'is_active' => $t->is_active,
                'cadence' => $this->cadenceLabel($t->recurrence_rrule),
                'recurrence' => $this->parseRrule($t->recurrence_rrule),
                'items' => $t->items_json ?? [],
                'generated_count' => $t->work_orders_count,
                'last_materialized_at' => $t->last_materialized_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/work-order-templates/index', [
            'templates' => $templates,
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
            'kinds' => collect(WorkOrderKind::cases())
                ->map(fn (WorkOrderKind $k) => ['value' => $k->value, 'label' => ucfirst(str_replace('_', ' ', $k->value))])
                ->all(),
            'actions' => collect(InventoryAction::cases())
                ->map(fn (InventoryAction $a) => ['value' => $a->value, 'label' => ucfirst($a->value)])
                ->all(),
            'filters' => ['venue_id' => $venueId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkOrderTemplate::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recurring template added.']);

        return back();
    }

    public function update(Request $request, WorkOrderTemplate $workOrderTemplate): RedirectResponse
    {
        $workOrderTemplate->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "'{$workOrderTemplate->name}' updated."]);

        return back();
    }

    public function destroy(WorkOrderTemplate $workOrderTemplate): RedirectResponse
    {
        $name = $workOrderTemplate->name;
        $workOrderTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "'{$name}' deleted."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::enum(WorkOrderKind::class)],
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:99'],
            'weekday' => ['required_if:frequency,weekly', Rule::in(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'])],
            'monthday' => ['required_if:frequency,monthly', 'integer', 'min:1', 'max:28'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'lookahead_days' => ['required', 'integer', 'min:1', 'max:90'],
            'default_assignee_role' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string', 'max:160'],
            'items.*.sku' => ['nullable', 'string', 'max:60'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'items.*.action' => ['required', Rule::enum(InventoryAction::class)],
        ]);

        return [
            'venue_id' => $data['venue_id'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'recurrence_rrule' => $this->composeRrule($data),
            'lookahead_days' => $data['lookahead_days'],
            'default_assignee_role' => $data['default_assignee_role'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'items_json' => array_values($data['items'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function composeRrule(array $d): string
    {
        $hour = (int) $d['hour'];
        $interval = (int) $d['interval'];

        if ($d['frequency'] === 'monthly') {
            return "FREQ=MONTHLY;INTERVAL={$interval};BYMONTHDAY={$d['monthday']};BYHOUR={$hour};BYMINUTE=0";
        }

        return "FREQ=WEEKLY;INTERVAL={$interval};BYDAY={$d['weekday']};BYHOUR={$hour};BYMINUTE=0";
    }

    /**
     * @return array<string, mixed>
     */
    private function parseRrule(?string $rrule): array
    {
        $get = function (string $key, string $default) use ($rrule): string {
            return preg_match("/{$key}=([^;]+)/", (string) $rrule, $m) ? $m[1] : $default;
        };

        $freq = strtoupper($get('FREQ', 'WEEKLY'));

        return [
            'frequency' => $freq === 'MONTHLY' ? 'monthly' : 'weekly',
            'interval' => (int) $get('INTERVAL', '1'),
            'weekday' => $get('BYDAY', 'MO'),
            'monthday' => (int) $get('BYMONTHDAY', '1'),
            'hour' => (int) $get('BYHOUR', '8'),
        ];
    }

    private function cadenceLabel(?string $rrule): string
    {
        $r = $this->parseRrule($rrule);
        $every = $r['interval'] > 1 ? "every {$r['interval']} " : 'every ';
        $hour = (int) $r['hour'];
        $time = sprintf('%d%s', $hour % 12 === 0 ? 12 : $hour % 12, $hour < 12 ? 'am' : 'pm');

        if ($r['frequency'] === 'monthly') {
            return $every.($r['interval'] > 1 ? 'months' : 'month')." on day {$r['monthday']} at {$time}";
        }

        $days = ['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'];

        return $every.($r['interval'] > 1 ? 'weeks' : 'week').' on '.($days[$r['weekday']] ?? $r['weekday'])." at {$time}";
    }
}
