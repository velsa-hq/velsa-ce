<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concerns\IsTaxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generic admin CRUD for any user-definable lookup taxonomy (space kinds,
 * departments, event kinds, ...). Subclasses supply the model and labels.
 * Route methods stay on the concrete controller so implicit route-model
 * binding resolves the right model from the parameter name.
 *
 * @see IsTaxonomy
 */
abstract class TaxonomyController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Inertia page component, e.g. 'admin/departments/index'. */
    abstract protected function component(): string;

    /** Relation name used for the in-use count + delete guard. */
    abstract protected function usageRelation(): string;

    /** Singular noun for flash + error messages, e.g. 'department'. */
    abstract protected function noun(): string;

    /** Whether the taxonomy carries a chip color (validated against COLORS). */
    protected function hasColor(): bool
    {
        return false;
    }

    /**
     * Extra Inertia props for the index page.
     *
     * @return array<string, mixed>
     */
    protected function extraProps(): array
    {
        return [];
    }

    /**
     * Extra model attributes a subclass wants persisted on store/update,
     * keyed by column. Pulled from the validated payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extraAttributes(array $data): array
    {
        return [];
    }

    /**
     * Extra fields a subclass wants on each row in the index payload.
     *
     * @return array<string, mixed>
     */
    protected function presentExtra(Model $taxon): array
    {
        return [];
    }

    protected function renderIndex(): Response
    {
        $model = $this->modelClass();
        $relation = $this->usageRelation();
        $countKey = Str::snake($relation).'_count';

        $items = $model::query()
            ->withCount($relation)
            ->ordered()
            ->get()
            ->map(fn (Model $t) => array_merge([
                'id' => $t->id,
                'key' => $t->key,
                'label' => $t->label,
                'color' => $this->hasColor() ? $t->color : null,
                'sort_order' => $t->sort_order,
                'is_active' => $t->is_active,
                'is_system' => $t->is_system,
                'usage_count' => $t->{$countKey} ?? 0,
            ], $this->presentExtra($t)))
            ->all();

        return Inertia::render($this->component(), array_merge([
            'items' => $items,
            'colors' => $this->hasColor() ? $model::COLORS : [],
        ], $this->extraProps()));
    }

    protected function storeTaxon(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $model = $this->modelClass();
        $key = Str::slug($data['label'], '_');

        if ($key === '' || $model::query()->where('key', $key)->exists()) {
            return back()->withErrors([
                'label' => "That {$this->noun()} already exists (or its name can't be turned into a unique key).",
            ]);
        }

        $model::query()->create(array_merge([
            'key' => $key,
            'label' => $data['label'],
            'sort_order' => (int) $model::query()->max('sort_order') + 1,
            'is_active' => true,
            'is_system' => false,
        ], $this->hasColor() ? ['color' => $data['color']] : [], $this->extraAttributes($data)));

        return back()->with('status', "{$this->label($data['label'])} added.");
    }

    protected function updateTaxon(Request $request, Model $taxon): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $taxon->update(array_merge(
            ['label' => $data['label']],
            $this->hasColor() ? ['color' => $data['color']] : [],
            $this->extraAttributes($data),
        ));

        return back()->with('status', "{$this->label($taxon->label)} updated.");
    }

    protected function toggleTaxon(Model $taxon): RedirectResponse
    {
        $taxon->update(['is_active' => ! $taxon->is_active]);

        $state = $taxon->is_active ? 'shown' : 'hidden';

        return back()->with('status', "{$this->label($taxon->label)} {$state}.");
    }

    protected function moveTaxon(Request $request, Model $taxon): RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $neighbor = $this->modelClass()::query()
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('sort_order', '<', $taxon->sort_order)->orderByDesc('sort_order'),
                fn ($q) => $q->where('sort_order', '>', $taxon->sort_order)->orderBy('sort_order'),
            )
            ->first();

        if ($neighbor !== null) {
            $self = $taxon->sort_order;
            $other = $neighbor->sort_order;

            if ($self === $other) {
                $other = $direction === 'up' ? $self - 1 : $self + 1;
            }

            $taxon->update(['sort_order' => $other]);
            $neighbor->update(['sort_order' => $self]);
        }

        return back();
    }

    protected function destroyTaxon(Model $taxon): RedirectResponse
    {
        if ($taxon->is_system) {
            return back()->withErrors([
                $this->noun() => "System {$this->noun()}s can't be deleted - deactivate it instead.",
            ]);
        }

        if ($taxon->{$this->usageRelation()}()->exists()) {
            return back()->withErrors([
                $this->noun() => "This {$this->noun()} is in use - reassign what uses it first.",
            ]);
        }

        $label = $taxon->label;
        $taxon->delete();

        return back()->with('status', "{$this->label($label)} deleted.");
    }

    /**
     * Validation rules for store + update.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = ['label' => ['required', 'string', 'max:60']];

        if ($this->hasColor()) {
            $rules['color'] = ['required', Rule::in($this->modelClass()::COLORS)];
        }

        return $rules;
    }

    /** Capitalized noun + label for messages, e.g. 'Department 'A/V''. */
    private function label(string $label): string
    {
        return Str::ucfirst($this->noun())." '{$label}'";
    }
}
