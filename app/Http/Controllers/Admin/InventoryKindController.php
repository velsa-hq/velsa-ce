<?php

namespace App\Http\Controllers\Admin;

use App\Models\InventoryKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Inventory-kind taxonomy CRUD. Logic in TaxonomyController; thin route
 * methods give implicit binding a typed {inventoryKind}.
 */
class InventoryKindController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return InventoryKind::class;
    }

    protected function component(): string
    {
        return 'admin/inventory-kinds/index';
    }

    protected function usageRelation(): string
    {
        return 'resources';
    }

    protected function noun(): string
    {
        return 'inventory kind';
    }

    public function index(): Response
    {
        return $this->renderIndex();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeTaxon($request);
    }

    public function update(Request $request, InventoryKind $inventoryKind): RedirectResponse
    {
        return $this->updateTaxon($request, $inventoryKind);
    }

    public function toggle(InventoryKind $inventoryKind): RedirectResponse
    {
        return $this->toggleTaxon($inventoryKind);
    }

    public function move(Request $request, InventoryKind $inventoryKind): RedirectResponse
    {
        return $this->moveTaxon($request, $inventoryKind);
    }

    public function destroy(InventoryKind $inventoryKind): RedirectResponse
    {
        return $this->destroyTaxon($inventoryKind);
    }
}
