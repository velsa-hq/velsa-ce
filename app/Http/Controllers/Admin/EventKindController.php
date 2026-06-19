<?php

namespace App\Http\Controllers\Admin;

use App\Models\EventKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Admin CRUD for the event-kind taxonomy. Logic lives in TaxonomyController;
 * the thin route methods exist so implicit binding resolves {eventKind}.
 */
class EventKindController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return EventKind::class;
    }

    protected function component(): string
    {
        return 'admin/event-kinds/index';
    }

    protected function usageRelation(): string
    {
        return 'bookings';
    }

    protected function noun(): string
    {
        return 'event kind';
    }

    public function index(): Response
    {
        return $this->renderIndex();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeTaxon($request);
    }

    public function update(Request $request, EventKind $eventKind): RedirectResponse
    {
        return $this->updateTaxon($request, $eventKind);
    }

    public function toggle(EventKind $eventKind): RedirectResponse
    {
        return $this->toggleTaxon($eventKind);
    }

    public function move(Request $request, EventKind $eventKind): RedirectResponse
    {
        return $this->moveTaxon($request, $eventKind);
    }

    public function destroy(EventKind $eventKind): RedirectResponse
    {
        return $this->destroyTaxon($eventKind);
    }
}
