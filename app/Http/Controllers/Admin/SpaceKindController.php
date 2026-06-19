<?php

namespace App\Http\Controllers\Admin;

use App\Models\SpaceKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Space-kind taxonomy CRUD. Logic lives in TaxonomyController; the thin route
 * methods exist so implicit route-model binding resolves {spaceKind}.
 */
class SpaceKindController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return SpaceKind::class;
    }

    protected function component(): string
    {
        return 'admin/space-kinds/index';
    }

    protected function usageRelation(): string
    {
        return 'spaces';
    }

    protected function noun(): string
    {
        return 'kind';
    }

    public function index(): Response
    {
        return $this->renderIndex();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeTaxon($request);
    }

    public function update(Request $request, SpaceKind $spaceKind): RedirectResponse
    {
        return $this->updateTaxon($request, $spaceKind);
    }

    public function toggle(SpaceKind $spaceKind): RedirectResponse
    {
        return $this->toggleTaxon($spaceKind);
    }

    public function move(Request $request, SpaceKind $spaceKind): RedirectResponse
    {
        return $this->moveTaxon($request, $spaceKind);
    }

    public function destroy(SpaceKind $spaceKind): RedirectResponse
    {
        return $this->destroyTaxon($spaceKind);
    }
}
