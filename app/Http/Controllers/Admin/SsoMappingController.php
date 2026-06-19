<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntraGroupRoleMapping;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Admin CRUD for Entra group -> Spatie role mappings consumed by the SSO
 * sign-in flow. Null venue_id means the role applies at every venue.
 */
class SsoMappingController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(): Response
    {
        $mappings = EntraGroupRoleMapping::query()
            ->with(['venue:id,name,slug'])
            ->orderBy('group_label')
            ->orderBy('role_name')
            ->get()
            ->map(fn (EntraGroupRoleMapping $m) => [
                'id' => $m->id,
                'entra_group_id' => $m->entra_group_id,
                'group_label' => $m->group_label,
                'role_name' => $m->role_name,
                'venue' => $m->venue
                    ? ['id' => $m->venue->id, 'name' => $m->venue->name, 'slug' => $m->venue->slug]
                    : null,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/sso-mappings/index', [
            'mappings' => $mappings,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sso-mappings/form', [
            'mode' => 'create',
            'mapping' => [
                'id' => null,
                'entra_group_id' => '',
                'group_label' => '',
                'role_name' => '',
                'venue_id' => null,
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMapping($request, ignoreId: null);

        /** @var User $user */
        $user = $request->user();
        $mapping = EntraGroupRoleMapping::create([
            ...$data,
            'created_by_user_id' => $user->id,
        ]);

        $this->audit->record(
            eventType: 'sso_mapping.created',
            subject: $mapping,
            payload: $data,
        );

        return to_route('admin.sso-mappings.index');
    }

    public function show(EntraGroupRoleMapping $mapping): Response
    {
        return Inertia::render('admin/sso-mappings/form', [
            'mode' => 'edit',
            'mapping' => [
                'id' => $mapping->id,
                'entra_group_id' => $mapping->entra_group_id,
                'group_label' => $mapping->group_label,
                'role_name' => $mapping->role_name,
                'venue_id' => $mapping->venue_id,
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function update(Request $request, EntraGroupRoleMapping $mapping): RedirectResponse
    {
        $data = $this->validateMapping($request, ignoreId: $mapping->id);

        $before = $mapping->only(['entra_group_id', 'group_label', 'role_name', 'venue_id']);
        $mapping->forceFill($data)->save();

        $this->audit->record(
            eventType: 'sso_mapping.updated',
            subject: $mapping,
            payload: ['before' => $before, 'after' => $data],
        );

        return back();
    }

    public function destroy(EntraGroupRoleMapping $mapping): RedirectResponse
    {
        $snapshot = $mapping->only(['id', 'entra_group_id', 'group_label', 'role_name', 'venue_id']);
        $mapping->delete();

        $this->audit->record(
            eventType: 'sso_mapping.deleted',
            payload: $snapshot,
        );

        return to_route('admin.sso-mappings.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateMapping(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'entra_group_id' => [
                'required',
                'string',
                // loose: accept GUID-shaped or longer string IDs
                'regex:/^[a-zA-Z0-9-]{16,}$/',
            ],
            'group_label' => ['nullable', 'string', 'max:255'],
            'role_name' => ['required', 'string', Rule::exists('roles', 'name')],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
        ]);

        // surface the (group, role, venue) unique-index as a 422 not a 500
        $collides = EntraGroupRoleMapping::query()
            ->where('entra_group_id', $data['entra_group_id'])
            ->where('role_name', $data['role_name'])
            ->where('venue_id', $data['venue_id'] ?? null)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        abort_if(
            $collides,
            422,
            'A mapping with this group, role, and venue already exists.',
        );

        return $data;
    }
}
