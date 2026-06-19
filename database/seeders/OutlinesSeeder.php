<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Adds an outline + per-department items to every Tentative/Definite/
 * Completed booking in the next 28 days. Idempotent.
 */
class OutlinesSeeder extends Seeder
{
    public function run(): void
    {
        if (EventOutline::query()->count() > 0) {
            $this->command?->info('OutlinesSeeder: outlines already present, skipping.');

            return;
        }

        $bookings = Booking::query()
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Tentative->value,
                BookingStatus::Completed->value,
            ])
            ->whereBetween('start_at', [now()->subDays(7), now()->addDays(28)])
            ->with('spaces.space')
            ->get();

        if ($bookings->isEmpty()) {
            $this->command?->warn('OutlinesSeeder: no bookings in the window. Run the booking seeders first.');

            return;
        }

        $itemCount = 0;
        foreach ($bookings as $booking) {
            $outline = EventOutline::query()->create([
                'booking_id' => $booking->id,
                'published_version' => 1,
                'published_at' => now()->subDays(random_int(0, 5)),
            ]);

            $start = $booking->start_at;
            if (! $start) {
                continue;
            }
            $space = $booking->spaces->first()?->space;

            $timeline = array_merge(
                $this->baseTimeline(),
                $this->kindTimeline((string) $booking->kind),
            );

            foreach ($timeline as $entry) {
                $itemAt = $start->copy()->addMinutes($entry['offset']);

                $item = OutlineItem::query()->create([
                    'event_outline_id' => $outline->id,
                    'space_id' => $space?->id,
                    'scheduled_at' => $itemAt,
                    'duration_minutes' => $entry['duration'],
                    'department' => $entry['dept'],
                    'title' => $entry['title'],
                    'description' => $entry['description'] ?? null,
                ]);
                $itemCount++;

                $this->seedChecklist($item, $entry['checklist'] ?? [], $itemAt);
            }
        }

        $this->command?->info("OutlinesSeeder: created {$bookings->count()} outlines with {$itemCount} items.");
    }

    /**
     * Materialize a checklist, done-state tracking the clock: past items
     * fully ticked, the ~2h window partial (last step open), future none.
     *
     * @param  list<string>  $checklist
     */
    protected function seedChecklist(OutlineItem $item, array $checklist, CarbonInterface $itemAt): void
    {
        $total = count($checklist);
        if ($total === 0) {
            return;
        }

        $doneMode = match (true) {
            $itemAt->isPast() => 'all',
            $itemAt->lt(now()->addHours(2)) => 'partial',
            default => 'none',
        };

        foreach ($checklist as $pos => $label) {
            $isDone = match ($doneMode) {
                'all' => true,
                'partial' => $pos < $total - 1,
                default => false,
            };

            $item->tasks()->create([
                'label' => $label,
                'position' => $pos,
                'is_done' => $isDone,
                'done_at' => $isDone ? $itemAt : null,
            ]);
        }
    }

    /**
     * Common run-of-show every booking gets, covering every
     * OutlineDepartment. The standardized ops items (huddle, setup, A/V,
     * catering, teardown) carry a Markdown description + checklist; flavor
     * items stay lean.
     *
     * @return list<array{offset:int,duration:int,dept:string,title:string,description?:string,checklist?:list<string>}>
     */
    protected function baseTimeline(): array
    {
        return [
            [
                'offset' => -180, 'duration' => 30, 'dept' => 'ops_lead',
                'title' => 'Pre-event ops huddle + run-of-show review',
                'description' => "Align every lead on the run-of-show, radio channels, and today's VIPs. **Everyone leaves the huddle knowing their first three moves.**",
                'checklist' => ['Confirm radio channels + spare batteries', 'Walk the run-of-show top to bottom', 'Assign zone leads', 'Note VIPs + special requests'],
            ],
            [
                'offset' => -150, 'duration' => 90, 'dept' => 'setup',
                'title' => 'Crew setup - chairs, tables, linens, AV cables',
                'description' => 'Flip the room to the approved floor plan. Cross-check against the diagram **before** doors.',
                'checklist' => ['Tables + linens to count', 'Chairs set to layout', 'AV cables run + taped down', 'Trash + recycling staged'],
            ],
            [
                'offset' => -60, 'duration' => 30, 'dept' => 'av',
                'title' => 'A/V sound check + mic test',
                'description' => 'Full line check on mics, playback, and monitors before doors - **no surprises during the program.**',
                'checklist' => ['Mics on + battery check', 'Walk-in / playback music', 'Confirm levels with FOH', 'Test presentation clicker'],
            ],
            ['offset' => -60, 'duration' => 30, 'dept' => 'cleaning', 'title' => 'Pre-event restroom + lobby touch-up'],
            [
                'offset' => -45, 'duration' => 45, 'dept' => 'catering',
                'title' => 'Catering load-in + chafing dishes',
                'description' => 'Receive catering, stage chafers, and reconcile counts against the BEO.',
                'checklist' => ['Verify guest count vs BEO', 'Chafing dishes lit', 'Beverage station stocked', 'Allergen labels placed'],
            ],
            ['offset' => -30, 'duration' => 30, 'dept' => 'parking', 'title' => 'Parking lot signage + attendant briefing'],
            ['offset' => -15, 'duration' => 15, 'dept' => 'reception', 'title' => 'Reception desk staffed + check-in lists posted'],
            ['offset' => 0, 'duration' => 30, 'dept' => 'reception', 'title' => 'Doors open · welcome guests'],
            ['offset' => 15, 'duration' => 15, 'dept' => 'ops_lead', 'title' => 'Ops lead - floor walk + radio check-in'],
            ['offset' => 60, 'duration' => 60, 'dept' => 'catering', 'title' => 'Main course service'],
            ['offset' => 120, 'duration' => 60, 'dept' => 'security', 'title' => 'Security perimeter sweep'],
            [
                'offset' => 240, 'duration' => 60, 'dept' => 'teardown',
                'title' => 'Teardown - strike tables, pack AV',
                'description' => 'Strike to a bare room, pack + inventory AV, and leave it better than we found it.',
                'checklist' => ['Strike tables + chairs', 'Pack + inventory AV', 'Lost & found swept', 'Final walkthrough with venue'],
            ],
            ['offset' => 300, 'duration' => 60, 'dept' => 'cleaning', 'title' => 'Final clean + venue walkthrough'],
        ];
    }

    /**
     * Department-specific items keyed on Booking.kind (sports gets a
     * scoreboard test, weddings a ceremony rehearsal, etc).
     *
     * @return list<array{offset:int,duration:int,dept:string,title:string}>
     */
    protected function kindTimeline(string $kind): array
    {
        return match ($kind) {
            'sports' => [
                ['offset' => -90, 'duration' => 30, 'dept' => 'av', 'title' => 'Scoreboard + clock check'],
                ['offset' => -45, 'duration' => 30, 'dept' => 'ops_lead', 'title' => 'Officials briefing + locker assignments'],
                ['offset' => 30, 'duration' => 30, 'dept' => 'security', 'title' => 'Bag check + crowd flow at gates'],
                ['offset' => 180, 'duration' => 30, 'dept' => 'cleaning', 'title' => 'Mid-tournament restroom restock'],
            ],
            'wedding', 'celebration' => [
                ['offset' => -240, 'duration' => 60, 'dept' => 'ops_lead', 'title' => 'Ceremony rehearsal walk-through'],
                ['offset' => -90, 'duration' => 30, 'dept' => 'setup', 'title' => 'Aisle runner + ceremony chairs'],
                ['offset' => 30, 'duration' => 15, 'dept' => 'av', 'title' => 'Ceremony music cue'],
                ['offset' => 180, 'duration' => 30, 'dept' => 'catering', 'title' => 'Cake cutting + dessert service'],
            ],
            'banquet', 'reception', 'fundraiser' => [
                ['offset' => -75, 'duration' => 30, 'dept' => 'setup', 'title' => 'Centerpieces + table numbers'],
                ['offset' => -30, 'duration' => 15, 'dept' => 'av', 'title' => 'House lights + presentation slides loaded'],
                ['offset' => 90, 'duration' => 30, 'dept' => 'ops_lead', 'title' => 'Program emcee handoff'],
            ],
            'conference', 'training' => [
                ['offset' => -45, 'duration' => 15, 'dept' => 'reception', 'title' => 'Print + stuff name-badge packets'],
                ['offset' => -30, 'duration' => 15, 'dept' => 'av', 'title' => 'Speaker laptop + clicker test'],
                ['offset' => 105, 'duration' => 30, 'dept' => 'catering', 'title' => 'Mid-morning coffee + pastry refresh'],
                ['offset' => 210, 'duration' => 30, 'dept' => 'catering', 'title' => 'Afternoon snack refresh'],
            ],
            'expo', 'trade_show' => [
                ['offset' => -300, 'duration' => 120, 'dept' => 'setup', 'title' => 'Booth pipe-and-drape + numbering'],
                ['offset' => -90, 'duration' => 60, 'dept' => 'setup', 'title' => 'Exhibitor early load-in window'],
                ['offset' => -30, 'duration' => 15, 'dept' => 'reception', 'title' => 'Exhibitor badge pickup'],
                ['offset' => 60, 'duration' => 30, 'dept' => 'security', 'title' => 'Aisle patrol + lost-and-found'],
            ],
            'festival', 'concert' => [
                ['offset' => -360, 'duration' => 180, 'dept' => 'setup', 'title' => 'Stage build + barricade'],
                ['offset' => -60, 'duration' => 30, 'dept' => 'av', 'title' => 'FOH sound check'],
                ['offset' => -30, 'duration' => 30, 'dept' => 'security', 'title' => 'Gate + wristband station setup'],
                ['offset' => 150, 'duration' => 60, 'dept' => 'cleaning', 'title' => 'Set-break grounds sweep'],
            ],
            'career_fair' => [
                ['offset' => -120, 'duration' => 60, 'dept' => 'setup', 'title' => 'Employer booths + signage'],
                ['offset' => -30, 'duration' => 30, 'dept' => 'reception', 'title' => 'Attendee badge printing'],
                ['offset' => 60, 'duration' => 30, 'dept' => 'ops_lead', 'title' => 'Employer mid-event check-in'],
            ],
            'retreat', 'community' => [
                ['offset' => -60, 'duration' => 30, 'dept' => 'ops_lead', 'title' => 'Group leader orientation'],
                ['offset' => 90, 'duration' => 60, 'dept' => 'catering', 'title' => 'Boxed lunch distribution'],
            ],
            'civic', 'performance' => [
                ['offset' => -90, 'duration' => 30, 'dept' => 'av', 'title' => 'Stage lighting cue rehearsal'],
                ['offset' => -30, 'duration' => 15, 'dept' => 'reception', 'title' => 'Program + ushers in position'],
                ['offset' => 90, 'duration' => 15, 'dept' => 'ops_lead', 'title' => 'Intermission floor sweep'],
            ],
            'networking' => [
                ['offset' => -60, 'duration' => 30, 'dept' => 'setup', 'title' => 'High-tops + bar rail setup'],
                ['offset' => -15, 'duration' => 15, 'dept' => 'reception', 'title' => 'Name-tag table + sign-in'],
                ['offset' => 45, 'duration' => 30, 'dept' => 'catering', 'title' => 'Bar restock + appetizer refresh'],
            ],
            default => [],
        };
    }
}
