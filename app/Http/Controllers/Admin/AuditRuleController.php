<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for audit rules - event-type prefixes that get flagged in the audit log.
 */
class AuditRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/audit-rules/index', [
            'rules' => AuditRule::query()
                ->orderBy('event_type')
                ->get(['id', 'name', 'event_type', 'description', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AuditRule::query()->create($this->validated($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Audit rule added.']);
    }

    public function update(Request $request, AuditRule $auditRule): RedirectResponse
    {
        $auditRule->update($this->validated($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Audit rule updated.']);
    }

    public function destroy(AuditRule $auditRule): RedirectResponse
    {
        $auditRule->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Audit rule removed.']);
    }

    /**
     * @return array{name:string, event_type:string, description:?string, is_active:bool}
     */
    private function validated(Request $request): array
    {
        /** @var array{name:string, event_type:string, description:?string, is_active:bool} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'event_type' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        return $data;
    }
}
