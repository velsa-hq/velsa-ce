<?php

namespace App\Http\Controllers;

use App\Models\ExhibitorEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExhibitorEventController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExhibitorEvent::class);

        $data = $this->validateEvent($request);
        $data['portal_slug'] = $this->resolveSlug($data['name'], $data['portal_slug'] ?? null);

        $event = ExhibitorEvent::query()->create($data);

        return redirect()
            ->route('exhibitor-events.show', $event)
            ->with('toast', ['type' => 'success', 'message' => "Created event '{$event->name}'."]);
    }

    public function update(Request $request, ExhibitorEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $this->validateEvent($request, $event);
        $data['portal_slug'] = $this->resolveSlug($data['name'], $data['portal_slug'] ?? null, $event);

        $event->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Event updated.']);
    }

    public function destroy(ExhibitorEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        if ($event->exhibitors()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete an event that still has exhibitors. Remove or reassign them first.',
            ]);
        }

        $name = $event->name;
        $event->delete();

        return redirect()
            ->route('exhibitors.index')
            ->with('toast', ['type' => 'success', 'message' => "Deleted event '{$name}'."]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateEvent(Request $request, ?ExhibitorEvent $event = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'portal_slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('exhibitor_events', 'portal_slug')->ignore($event?->id),
            ],
            'default_booth_size' => ['nullable', 'string', 'max:40'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'advance_rate_deadline' => ['nullable', 'date'],
            'late_order_surcharge_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $data['late_order_surcharge_pct'] = $data['late_order_surcharge_pct'] ?? 0;

        return $data;
    }

    /**
     * Use the supplied slug if present, otherwise derive a unique one
     * from the event name (suffixing -2, -3, ... on collision).
     */
    protected function resolveSlug(string $name, ?string $slug, ?ExhibitorEvent $ignore = null): string
    {
        $base = Str::slug($slug ?: $name) ?: 'event';
        $candidate = $base;
        $n = 1;

        while (ExhibitorEvent::query()
            ->where('portal_slug', $candidate)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists()
        ) {
            $n++;
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }
}
