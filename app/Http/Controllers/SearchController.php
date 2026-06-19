<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global header search. Fans one query across the Scout-indexed models,
 * normalizes hits to a uniform shape, and groups by record type. Capped
 * per group so one high-frequency type can't drown out the others.
 */
class SearchController extends Controller
{
    protected const PER_GROUP = 5;

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if ($query === '') {
            return response()->json(['query' => '', 'groups' => []]);
        }

        // search must honor the same RBAC as direct navigation: only search/return
        // a type when the user holds the matching permission, so search can't be a
        // side channel around RBAC. hasVenuePermission is memoized per request
        $user = $request->user();
        $can = fn (string $permission): bool => $user !== null && $user->hasVenuePermission($permission);

        $candidates = [
            ['perm' => 'bookings.view',     'key' => 'bookings',   'label' => 'Bookings',   'icon' => 'CalendarDays',   'method' => 'bookings'],
            ['perm' => 'clients.view',      'key' => 'clients',    'label' => 'Clients',    'icon' => 'Briefcase',      'method' => 'clients'],
            ['perm' => 'exhibitors.manage', 'key' => 'exhibitors', 'label' => 'Exhibitors', 'icon' => 'Store',          'method' => 'exhibitors'],
            ['perm' => 'accounting.view',   'key' => 'invoices',   'label' => 'Invoices',   'icon' => 'Receipt',        'method' => 'invoices'],
            ['perm' => 'contracts.view',    'key' => 'contracts',  'label' => 'Contracts',  'icon' => 'FileSignature',  'method' => 'contracts'],
            ['perm' => 'venues.view',       'key' => 'venues',     'label' => 'Venues',     'icon' => 'Building2',       'method' => 'venues'],
            ['perm' => 'spaces.view',       'key' => 'spaces',     'label' => 'Spaces',     'icon' => 'DoorOpen',        'method' => 'spaces'],
            ['perm' => 'venues.view',       'key' => 'equipment',  'label' => 'Equipment',  'icon' => 'Package',         'method' => 'equipment'],
        ];

        $groups = [];
        foreach ($candidates as $c) {
            if (! $can($c['perm'])) {
                continue;
            }
            $groups[] = [
                'key' => $c['key'],
                'label' => $c['label'],
                'icon' => $c['icon'],
                'results' => $this->{$c['method']}($query),
            ];
        }

        // drop empty groups so the UI doesn't render childless headers
        $groups = array_values(array_filter(
            $groups,
            fn (array $g) => ! empty($g['results']),
        ));

        return response()->json([
            'query' => $query,
            'groups' => $groups,
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function bookings(string $query): array
    {
        return Booking::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'title' => $b->name ?? $b->reference,
                'subtitle' => trim(implode(' · ', array_filter([
                    $b->reference,
                    $b->client?->name,
                    $b->venue?->name,
                ]))),
                'badge' => $b->status?->value,
                'url' => "/bookings/{$b->id}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function clients(string $query): array
    {
        return Client::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $c->type?->value,
                    $c->industry,
                ]))),
                'badge' => null,
                'url' => "/clients/{$c->id}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function exhibitors(string $query): array
    {
        return Exhibitor::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Exhibitor $e) => [
                'id' => $e->id,
                'title' => $e->company_name ?? $e->contact_name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $e->contact_name,
                    $e->email,
                    $e->event?->name,
                ]))),
                'badge' => $e->booth_assignment,
                'url' => "/exhibitors/{$e->id}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function invoices(string $query): array
    {
        return Invoice::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(function (Invoice $i) {
                $source = $i->invoiceable;
                $sourceName = null;
                if ($source instanceof Booking) {
                    $sourceName = trim(($source->client?->name ?? '').' '.$source->reference);
                } elseif ($source instanceof ExhibitorOrder) {
                    $sourceName = $source->exhibitor?->company_name;
                }

                return [
                    'id' => $i->id,
                    'title' => $i->number,
                    'subtitle' => $sourceName,
                    'badge' => $i->status?->value,
                    'url' => "/admin/invoices/{$i->number}",
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function contracts(string $query): array
    {
        return Contract::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'title' => $c->reference,
                'subtitle' => trim(implode(' · ', array_filter([
                    $c->booking?->name,
                    $c->booking?->client?->name,
                ]))),
                'badge' => $c->status?->value,
                'url' => "/contracts/{$c->id}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function venues(string $query): array
    {
        return Venue::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Venue $v) => [
                'id' => $v->id,
                'title' => $v->name,
                'subtitle' => trim(implode(', ', array_filter([
                    $v->address_json['city'] ?? null,
                    $v->address_json['state'] ?? null,
                ]))),
                'badge' => null,
                'url' => "/venues/{$v->slug}",
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function spaces(string $query): array
    {
        return Space::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (Space $s) => [
                'id' => $s->id,
                'title' => $s->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $s->venue?->name,
                    $s->kind,
                ]))),
                'badge' => $s->capacity ? "cap {$s->capacity}" : null,
                'url' => $s->venue?->slug ? "/venues/{$s->venue->slug}" : '#',
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function equipment(string $query): array
    {
        return EquipmentItem::search($query)
            ->take(self::PER_GROUP)
            ->get()
            ->map(fn (EquipmentItem $e) => [
                'id' => $e->id,
                'title' => $e->name,
                'subtitle' => trim(implode(' · ', array_filter([
                    $e->sku,
                    $e->category?->name,
                ]))),
                'badge' => $e->is_active ? null : 'inactive',
                'url' => '#',
            ])
            ->all();
    }
}
