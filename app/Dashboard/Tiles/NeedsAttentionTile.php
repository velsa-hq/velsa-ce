<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\BookingStatus;
use App\Enums\ContractStatus;
use App\Enums\LeadStage;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Stale-record signals: cold tentative bookings, unopened sent contracts, stuck leads.
 * Day windows are tunable via the defaults.needs_attention_* settings.
 */
class NeedsAttentionTile extends DashboardTile
{
    private const ITEM_LIMIT = 5;

    private function bookingStaleDays(): int
    {
        return (int) app(SystemSettings::class)->get('defaults.needs_attention_booking_stale_days', 14);
    }

    private function contractUnviewedDays(): int
    {
        return (int) app(SystemSettings::class)->get('defaults.needs_attention_contract_unviewed_days', 7);
    }

    private function leadStuckDays(): int
    {
        return (int) app(SystemSettings::class)->get('defaults.needs_attention_lead_stuck_days', 14);
    }

    public function key(): string
    {
        return 'needs_attention';
    }

    public function label(): string
    {
        return 'Needs attention';
    }

    public function description(): string
    {
        return 'Stale records that want a follow-up: cold tentative bookings, unopened sent contracts, and leads stuck after a contract was sent.';
    }

    public function columnSpan(): int
    {
        return 6;
    }

    public function permission(): ?string
    {
        return 'bookings.view';
    }

    public function render(User $user): array
    {
        return [
            'groups' => [
                $this->staleTentativeBookings(),
                $this->unviewedSentContracts(),
                $this->stuckLeads(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staleTentativeBookings(): array
    {
        $days = $this->bookingStaleDays();
        $cutoff = now()->subDays($days);

        $base = Booking::query()
            ->where('status', BookingStatus::Tentative->value)
            ->whereDoesntHave(
                'narratives',
                fn (Builder $q) => $q->where('happened_at', '>=', $cutoff),
            )
            // require time to have actually elapsed: has an older narrative, or
            // was created before the cutoff - else a brand-new tentative reads as stale 0d
            ->where(
                fn (Builder $q) => $q
                    ->whereHas('narratives')
                    ->orWhere('created_at', '<', $cutoff),
            );

        $items = (clone $base)
            ->withMax('narratives as last_activity_at', 'happened_at')
            ->orderBy('start_at')
            ->limit(self::ITEM_LIMIT)
            ->get()
            ->map(function (Booking $b) {
                // withMax returns a raw datetime string, not a Carbon cast
                $lastActivity = $b->getAttribute('last_activity_at');
                $last = $lastActivity !== null
                    ? Carbon::parse($lastActivity)
                    : $b->created_at;

                return [
                    'id' => $b->id,
                    'label' => $b->name,
                    'days' => (int) $last->diffInDays(now()),
                    'href' => "/bookings/{$b->id}",
                ];
            })
            ->all();

        return [
            'key' => 'stale_tentative_bookings',
            'label' => 'Tentative bookings going cold',
            'count' => (clone $base)->count(),
            'unit' => 'no activity in '.$days.'d',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unviewedSentContracts(): array
    {
        $days = $this->contractUnviewedDays();
        $cutoff = now()->subDays($days);

        // status flips to "viewed" on open, so still-sent + old sent_at == never opened
        $base = Contract::query()
            ->where('status', ContractStatus::Sent->value)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<', $cutoff);

        $items = (clone $base)
            ->orderBy('sent_at')
            ->limit(self::ITEM_LIMIT)
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'label' => $c->reference,
                'days' => (int) Carbon::parse($c->sent_at)->diffInDays(now()),
                'href' => "/contracts/{$c->id}",
            ])
            ->all();

        return [
            'key' => 'unviewed_sent_contracts',
            'label' => 'Contracts sent, not opened',
            'count' => (clone $base)->count(),
            'unit' => 'unopened '.$days.'d+',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stuckLeads(): array
    {
        $days = $this->leadStuckDays();
        $cutoff = now()->subDays($days);

        // no stage-entered timestamp on leads; updated_at is the best proxy for "hasn't moved"
        $base = Lead::query()
            ->where('stage', LeadStage::ContractSent->value)
            ->where('updated_at', '<', $cutoff);

        $items = (clone $base)
            ->orderBy('updated_at')
            ->limit(self::ITEM_LIMIT)
            ->get()
            ->map(fn (Lead $l) => [
                'id' => $l->id,
                'label' => $l->name,
                'days' => (int) $l->updated_at->diffInDays(now()),
                'href' => "/leads/{$l->id}",
            ])
            ->all();

        return [
            'key' => 'stuck_leads',
            'label' => 'Leads stuck after contract sent',
            'count' => (clone $base)->count(),
            'unit' => 'no movement '.$days.'d+',
            'items' => $items,
        ];
    }
}
