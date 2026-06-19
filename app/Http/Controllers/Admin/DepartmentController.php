<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Department taxonomy CRUD. Logic lives in TaxonomyController; the thin route
 * methods exist so implicit route-model binding resolves {department}.
 */
class DepartmentController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function component(): string
    {
        return 'admin/departments/index';
    }

    protected function usageRelation(): string
    {
        return 'outlineItems';
    }

    protected function noun(): string
    {
        return 'department';
    }

    protected function hasColor(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return array_merge(parent::rules(), [
            'default_role' => ['nullable', 'string', 'max:60', Rule::exists('roles', 'name')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extraAttributes(array $data): array
    {
        return ['default_role' => $data['default_role'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentExtra(Model $taxon): array
    {
        return ['default_role' => $taxon->getAttribute('default_role')];
    }

    /**
     * Surface the assignable role names so the admin can pick a default
     * crew role per department (generated work orders auto-assign to it).
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return ['roles' => Role::query()->orderBy('name')->pluck('name')->all()];
    }

    public function index(): Response
    {
        return $this->renderIndex();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeTaxon($request);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        return $this->updateTaxon($request, $department);
    }

    public function toggle(Department $department): RedirectResponse
    {
        return $this->toggleTaxon($department);
    }

    public function move(Request $request, Department $department): RedirectResponse
    {
        return $this->moveTaxon($request, $department);
    }

    public function destroy(Department $department): RedirectResponse
    {
        return $this->destroyTaxon($department);
    }
}
