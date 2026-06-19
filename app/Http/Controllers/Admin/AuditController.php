<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\AuditRule;
use App\Models\User;
use App\Models\Venue;
use App\Support\Csv;
use App\Support\DateFormatter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureAuditViewAccess($request->user());

        $canSeeRaw = $this->userCanRaw($request->user());

        $filters = [
            'event_type' => $request->string('event_type')->toString() ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'venue_id' => $request->integer('venue_id') ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'flagged' => $request->boolean('flagged'),
        ];

        // events matching an active rule prefix are flagged
        $rulePrefixes = AuditRule::query()->where('is_active', true)->pluck('event_type')->all();

        $events = AuditEvent::query()
            ->with(['user:id,name,email', 'venue:id,name,slug'])
            ->when($filters['event_type'], fn ($q, $v) => $q->where('event_type', 'like', "{$v}%"))
            ->when($filters['user_id'], fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['venue_id'], fn ($q, $v) => $q->where('venue_id', $v))
            ->when($filters['from'], fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'], fn ($q, $v) => $q->where('created_at', '<', date('Y-m-d', strtotime("{$v} +1 day"))))
            ->when($filters['flagged'] && $rulePrefixes !== [], fn ($q) => $q->where(function ($w) use ($rulePrefixes) {
                foreach ($rulePrefixes as $prefix) {
                    $w->orWhere('event_type', 'like', "{$prefix}%");
                }
            }))
            ->when($filters['flagged'] && $rulePrefixes === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $rows = $events->getCollection()->map(fn (AuditEvent $event) => [
            'id' => $event->id,
            'created_at' => $event->created_at?->toIso8601String(),
            'event_type' => $event->event_type,
            'subject_type' => $event->subject_type ? class_basename($event->subject_type) : null,
            'subject_id' => $event->subject_id,
            'ip' => $event->ip,
            'user' => $event->user ? [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ] : null,
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'slug' => $event->venue->slug,
            ] : null,
            'flagged' => $this->matchesRule($event->event_type, $rulePrefixes),
            'payload' => $canSeeRaw ? $event->payload_json : $event->maskedPayload(),
        ]);

        return Inertia::render('admin/audit/index', [
            'events' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
                'links' => [
                    'prev' => $events->previousPageUrl(),
                    'next' => $events->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'users' => User::query()->orderBy('email')->get(['id', 'name', 'email']),
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'can_see_raw' => $canSeeRaw,
        ]);
    }

    /**
     * Whether an event type matches any active audit-rule prefix.
     *
     * @param  list<string>  $prefixes
     */
    private function matchesRule(string $eventType, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($eventType, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->ensureAuditViewAccess($request->user());
        $canSeeRaw = $this->userCanRaw($request->user());

        $query = AuditEvent::query()
            ->with(['user:id,email', 'venue:id,slug'])
            ->when($request->string('event_type')->toString(), fn ($q, $v) => $q->where('event_type', 'like', "{$v}%"))
            ->when($request->integer('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->integer('venue_id'), fn ($q, $v) => $q->where('venue_id', $v))
            ->when($request->date('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->date('to'), fn ($q, $v) => $q->where('created_at', '<', $v->copy()->addDay()))
            ->latest('created_at');

        $filename = 'audit-events-'.DateFormatter::fileStamp().'.csv';

        return response()->streamDownload(function () use ($query, $canSeeRaw) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'event_type', 'user_email', 'venue_slug', 'subject_type', 'subject_id', 'ip', 'payload_json']);

            $query->chunk(500, function ($events) use ($out, $canSeeRaw): void {
                foreach ($events as $event) {
                    $payload = $canSeeRaw ? $event->payload_json : $event->maskedPayload();
                    fputcsv($out, Csv::row([
                        $event->id,
                        $event->created_at?->toIso8601String(),
                        $event->event_type,
                        $event->user?->email,
                        $event->venue?->slug,
                        $event->subject_type,
                        $event->subject_id,
                        $event->ip,
                        $payload ? json_encode($payload) : null,
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * User must hold audit.view at any venue.
     */
    protected function ensureAuditViewAccess(?User $user): void
    {
        abort_unless($user !== null && $this->userCanAtAnyVenue($user, 'audit.view'), 403);
    }

    protected function userCanRaw(?User $user): bool
    {
        return $user !== null && $this->userCanAtAnyVenue($user, 'audit.export.raw');
    }

    protected function userCanAtAnyVenue(User $user, string $permission): bool
    {
        foreach ($user->accessibleVenues() as $venue) {
            if ($user->canAt($venue, $permission)) {
                return true;
            }
        }

        return false;
    }
}
