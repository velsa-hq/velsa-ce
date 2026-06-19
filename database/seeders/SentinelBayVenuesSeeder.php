<?php

namespace Database\Seeders;

use App\Enums\BookableUnit;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo venue portfolio: five active venues + one "coming online soon" to
 * exercise the inactive-venue paths. Spaces with movable walls use the
 * parent_space_id hierarchy for the partition-subset rule.
 *
 * Idempotent: keyed on slug for venues and on (venue, name) for spaces.
 */
class SentinelBayVenuesSeeder extends Seeder
{
    public function run(): void
    {
        $activeAt = Carbon::parse('2020-01-01');

        // 1. Pelican Cove Convention Center - the flagship. Coral Reef Grand
        //    Ballroom uses a 4-section partition layout plus a small-room +
        //    outdoor mix.
        $this->seedVenue(
            slug: 'pelican-cove-convention-center',
            name: 'Pelican Cove Convention Center',
            address: ['city' => 'Pelican Cove', 'state' => 'FL'],
            settings: [
                'catering' => ['exclusive_contractor' => true],
                'summary' => '22,000 sqft Coral Reef Grand Ballroom split into four sections + Exhibit Hall A (any contiguous combination bookable), six small meeting rooms (Tide, Sunrise, Coral - each partitionable - plus Lighthouse and Mariner), 32,000 sqft outdoor lawn, and the Bayfront Terrace.',
            ],
            activeAt: $activeAt,
            spaces: [
                ['name' => 'Coral Reef Grand Ballroom + Exhibit Hall A', 'kind' => 'ballroom', 'sqft' => 11_000, 'capacity' => 800, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Coral Reef Grand Ballroom B', 'kind' => 'ballroom', 'sqft' => 5_500, 'capacity' => 400, 'bookable_unit' => BookableUnit::Daily, 'parent' => 'Coral Reef Grand Ballroom + Exhibit Hall A'],
                ['name' => 'Coral Reef Grand Ballroom C', 'kind' => 'ballroom', 'sqft' => 2_750, 'capacity' => 200, 'bookable_unit' => BookableUnit::Daily, 'parent' => 'Coral Reef Grand Ballroom B'],
                ['name' => 'Coral Reef Grand Ballroom D', 'kind' => 'ballroom', 'sqft' => 2_750, 'capacity' => 200, 'bookable_unit' => BookableUnit::Daily, 'parent' => 'Coral Reef Grand Ballroom B'],

                ['name' => 'Tide 1', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Tide 2', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly, 'parent' => 'Tide 1'],

                ['name' => 'Sunrise 1', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Sunrise 2', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly, 'parent' => 'Sunrise 1'],

                ['name' => 'Coral 1', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Coral 2', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly, 'parent' => 'Coral 1'],

                ['name' => 'Lighthouse', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Mariner', 'kind' => 'room', 'sqft' => 420, 'capacity' => 28, 'bookable_unit' => BookableUnit::Hourly],

                ['name' => 'Bayfront Lawn', 'kind' => 'outdoor_field', 'sqft' => 32_000, 'capacity' => 2_200, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Bayfront Terrace', 'kind' => 'terrace', 'sqft' => 1_350, 'capacity' => 90, 'bookable_unit' => BookableUnit::Daily],
            ],
        );

        // 2. Aquila Performing Arts Hall - fixed-seat performance hall with
        //    rentable rehearsal / reception spaces; adds venue.kind variety.
        $this->seedVenue(
            slug: 'aquila-performing-arts-hall',
            name: 'Aquila Performing Arts Hall',
            address: ['city' => 'Pelican Cove', 'state' => 'FL'],
            settings: [
                'summary' => '1,800-seat main theater with proscenium stage, 280-seat black box, three rehearsal rooms, a green-room suite, and a pre-function lobby suitable for receptions before or after performances.',
            ],
            activeAt: $activeAt,
            spaces: [
                ['name' => 'Main Theater', 'kind' => 'zone', 'sqft' => 16_500, 'capacity' => 1_800, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Black Box Theater', 'kind' => 'zone', 'sqft' => 2_400, 'capacity' => 280, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Rehearsal Room 1', 'kind' => 'room', 'sqft' => 900, 'capacity' => 40, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Rehearsal Room 2', 'kind' => 'room', 'sqft' => 900, 'capacity' => 40, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Rehearsal Room 3', 'kind' => 'room', 'sqft' => 900, 'capacity' => 40, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Green Room Suite', 'kind' => 'room', 'sqft' => 600, 'capacity' => 24, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Pre-Function Lobby', 'kind' => 'zone', 'sqft' => 3_200, 'capacity' => 350, 'bookable_unit' => BookableUnit::Daily],
            ],
        );

        // 3. Sentinel Bay Sports & Recreation Complex - arena plus outdoor
        //    tournament fields; mixes Arena and OutdoorField kinds.
        $this->seedVenue(
            slug: 'sentinel-bay-sports-recreation-complex',
            name: 'Sentinel Bay Sports & Recreation Complex',
            address: ['city' => 'Driftwood', 'state' => 'FL'],
            settings: [
                'summary' => '4,500-seat Sentinel Bay Arena with NCAA-spec hardwood, a practice gym, three multi-purpose tournament fields, a locker-room block, and two hospitality suites overlooking the arena floor.',
            ],
            activeAt: $activeAt,
            spaces: [
                ['name' => 'Sentinel Bay Arena', 'kind' => 'arena', 'sqft' => 28_000, 'capacity' => 4_500, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Practice Gym', 'kind' => 'arena', 'sqft' => 9_500, 'capacity' => 350, 'bookable_unit' => BookableUnit::Hourly],
                ['name' => 'Tournament Field North', 'kind' => 'outdoor_field', 'sqft' => 80_000, 'capacity' => 2_500, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Tournament Field Center', 'kind' => 'outdoor_field', 'sqft' => 80_000, 'capacity' => 2_500, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Tournament Field South', 'kind' => 'outdoor_field', 'sqft' => 80_000, 'capacity' => 2_500, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Locker Room Block', 'kind' => 'zone', 'sqft' => 4_200, 'capacity' => 120, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Hospitality Suite A', 'kind' => 'room', 'sqft' => 850, 'capacity' => 36, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Hospitality Suite B', 'kind' => 'room', 'sqft' => 850, 'capacity' => 36, 'bookable_unit' => BookableUnit::Daily],
            ],
        );

        // 4. Driftwood Fairgrounds - livestock + RV infrastructure
        $this->seedVenue(
            slug: 'driftwood-fairgrounds',
            name: 'Driftwood Fairgrounds',
            address: ['city' => 'Driftwood', 'state' => 'FL'],
            settings: [
                'summary' => '45-acre fairgrounds with a 32,000 sqft indoor expo hall, festival-scale outdoor grounds, a 3,200-seat livestock arena, a 120-stall barn block, 60 RV pads with 50A hookups, and a concession + multi-purpose hall.',
            ],
            activeAt: $activeAt,
            spaces: [
                ['name' => 'Indoor Expo Hall', 'kind' => 'zone', 'sqft' => 32_000, 'capacity' => 2_500, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Outdoor Festival Grounds', 'kind' => 'outdoor_field', 'sqft' => 60_000, 'capacity' => 6_000, 'bookable_unit' => BookableUnit::MultiDay],
                ['name' => 'Livestock Arena', 'kind' => 'arena', 'capacity' => 3_200, 'bookable_unit' => BookableUnit::MultiDay],
                ['name' => 'Stall Block', 'kind' => 'stall', 'capacity' => 120, 'bookable_unit' => BookableUnit::MultiDay, 'attributes_json' => ['stall_count' => 120, 'size' => '10x10']],
                ['name' => 'RV Camping', 'kind' => 'rv_pad', 'bookable_unit' => BookableUnit::MultiDay, 'attributes_json' => ['hookup_type' => '50A', 'pad_count' => 60]],
                ['name' => 'Concession + Multi-Purpose Hall', 'kind' => 'zone', 'sqft' => 3_800, 'capacity' => 180, 'bookable_unit' => BookableUnit::Daily],
            ],
        );

        // 5. Heron Creek Retreat - open-air retreat with cabins; weddings,
        //    reunions, retreats.
        $this->seedVenue(
            slug: 'heron-creek-retreat',
            name: 'Heron Creek Retreat',
            address: ['city' => 'Heron Creek', 'state' => 'FL'],
            settings: [
                'summary' => '38 acres of lake-side green space with a covered pavilion (240 guests), an open-pole barn, three lakeside cabins, and dedicated picnic + ceremony zones. Weddings, reunions, retreats, small festivals.',
            ],
            activeAt: $activeAt,
            spaces: [
                ['name' => 'Lakeside Pavilion', 'kind' => 'barn', 'capacity' => 240, 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Open-Pole Barn', 'kind' => 'barn', 'bookable_unit' => BookableUnit::Daily],
                ['name' => 'Heron Cabin 1', 'kind' => 'cabin', 'capacity' => 8, 'bookable_unit' => BookableUnit::MultiDay],
                ['name' => 'Heron Cabin 2', 'kind' => 'cabin', 'capacity' => 8, 'bookable_unit' => BookableUnit::MultiDay],
                ['name' => 'Heron Cabin 3', 'kind' => 'cabin', 'capacity' => 8, 'bookable_unit' => BookableUnit::MultiDay],
                ['name' => 'Ceremony Lawn', 'kind' => 'zone', 'bookable_unit' => BookableUnit::Daily],
            ],
        );

        // 6. Marlin Bay Welcome Center - coming online soon; exercises the
        //    inactive-venue paths (filters out of /ops/board, /ops/schedule,
        //    pipeline target picker, etc.)
        $this->seedVenue(
            slug: 'marlin-bay-welcome-center',
            name: 'Marlin Bay Welcome Center',
            address: ['state' => 'FL'],
            settings: ['summary' => 'Coming online soon.'],
            activeAt: null,
            spaces: [],
        );
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $spaces
     */
    protected function seedVenue(
        string $slug,
        string $name,
        array $address,
        array $settings,
        ?Carbon $activeAt,
        array $spaces,
    ): void {
        $venue = Venue::withTrashed()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'address_json' => $address,
                'timezone' => 'America/Chicago',
                'settings_json' => $settings,
                'active_at' => $activeAt,
            ],
        );

        $nameToId = [];

        foreach ($spaces as $space) {
            $row = Space::withTrashed()->updateOrCreate(
                ['venue_id' => $venue->id, 'name' => $space['name']],
                [
                    'kind' => $space['kind'],
                    'capacity' => $space['capacity'] ?? null,
                    'sqft' => $space['sqft'] ?? null,
                    'bookable_unit' => ($space['bookable_unit'] ?? BookableUnit::Daily)->value,
                    'attributes_json' => $space['attributes_json'] ?? null,
                ],
            );
            $nameToId[$space['name']] = $row->id;
        }

        foreach ($spaces as $space) {
            if (! isset($space['parent'])) {
                Space::query()
                    ->where('venue_id', $venue->id)
                    ->where('name', $space['name'])
                    ->update(['parent_space_id' => null]);

                continue;
            }

            $parentId = $nameToId[$space['parent']]
                ?? throw new \RuntimeException("Space '{$space['name']}' references unknown parent '{$space['parent']}'.");

            Space::query()
                ->where('venue_id', $venue->id)
                ->where('name', $space['name'])
                ->update(['parent_space_id' => $parentId]);
        }
    }
}
