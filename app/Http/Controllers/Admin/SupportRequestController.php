<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;

        $requests = SupportRequest::query()
            ->with(['user:id,name,email', 'resolver:id,name,email'])
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $rows = $requests->getCollection()->map(fn (SupportRequest $r) => [
            'id' => $r->id,
            'category' => $r->category->value,
            'category_label' => $r->category->label(),
            'subject' => $r->subject,
            'body' => $r->body,
            'page_url' => $r->page_url,
            'app_version' => $r->app_version,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'created_at' => $r->created_at->toIso8601String(),
            'resolved_at' => $r->resolved_at?->toIso8601String(),
            'user' => $r->user ? [
                'id' => $r->user->id,
                'name' => $r->user->name,
                'email' => $r->user->email,
            ] : null,
            'resolver' => $r->resolver ? [
                'name' => $r->resolver->name,
                'email' => $r->resolver->email,
            ] : null,
        ]);

        return Inertia::render('admin/support-requests/index', [
            'requests' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ],
                'links' => [
                    'prev' => $requests->previousPageUrl(),
                    'next' => $requests->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $status],
            'open_count' => SupportRequest::query()->where('status', SupportRequestStatus::Open)->count(),
        ]);
    }

    public function update(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(SupportRequestStatus::class)],
        ]);

        $status = SupportRequestStatus::from($validated['status']);

        $supportRequest->status = $status;

        if ($status === SupportRequestStatus::Closed) {
            $supportRequest->resolved_at = now();
            $supportRequest->resolved_by = $request->user()?->id;
        } else {
            $supportRequest->resolved_at = null;
            $supportRequest->resolved_by = null;
        }

        $supportRequest->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Request marked {$status->label()}.",
        ]);
    }
}
