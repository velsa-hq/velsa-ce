<?php

namespace Database\Seeders;

use App\Enums\BookableUnit;
use App\Enums\BookingStatus;
use App\Enums\RateCardKind;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\RateCard;
use App\Models\RateCardEntry;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo booking calendar: a mix across all five active venues and every
 * status, plus a 2026 standard rate card per venue.
 *
 * Idempotent: skips if 10+ bookings already exist.
 */
class SentinelBayBookingsSeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::query()->count() >= 10) {
            $this->command?->info('SentinelBayBookingsSeeder: existing bookings present, skipping.');

            return;
        }

        $venues = Venue::query()->active()->with('spaces')->get()->keyBy('slug');
        if ($venues->isEmpty()) {
            $this->command?->warn('SentinelBayBookingsSeeder: no active venues. Run SentinelBayVenuesSeeder first.');

            return;
        }

        $clients = Client::query()->get()->keyBy('name');
        if ($clients->isEmpty()) {
            $this->command?->warn('SentinelBayBookingsSeeder: no clients. Run SentinelBaySalesSeeder first.');

            return;
        }

        $this->seedRateCards($venues);

        // round-robin owners across the Events team if present
        $eventsTeam = User::query()
            ->whereIn('email', [
                'maya.chen@sentinelbay.ca.gov',
                'eli.rodriguez@sentinelbay.ca.gov',
                'sam.park@sentinelbay.ca.gov',
            ])
            ->get();
        if ($eventsTeam->isEmpty()) {
            $eventsTeam = collect([User::query()->orderBy('id')->first()])->filter();
        }

        $created = 0;
        foreach ($this->eventCalendar() as $idx => $event) {
            $venue = $venues->get($event['venue']);
            if (! $venue) {
                continue;
            }

            $eventSpaces = collect($event['spaces'] ?? [])
                ->map(fn (string $n) => $venue->spaces->firstWhere('name', $n))
                ->filter()
                ->values();

            if ($eventSpaces->isEmpty()) {
                $eventSpaces = collect([$venue->spaces->random()]);
            }

            $client = $clients->get($event['client']) ?? $clients->random();
            $owner = $eventsTeam->isEmpty() ? null : $eventsTeam[$idx % $eventsTeam->count()];

            $start = Carbon::parse($event['start']);
            $end = Carbon::parse($event['end']);

            $booking = Booking::query()->create([
                'venue_id' => $venue->id,
                'client_id' => $client->id,
                'owner_user_id' => $owner?->id,
                'name' => $event['name'],
                'kind' => $event['kind'],
                'status' => $event['status']->value,
                'start_at' => $start,
                'end_at' => $end,
                'total_cents' => $event['total_cents'],
                'deposit_percent' => $event['deposit_percent'] ?? 50,
                'attendance_estimate' => $event['attendance'],
                'cancelled_at' => $event['status'] === BookingStatus::Cancelled ? $start->copy()->subDays(7) : null,
                'cancel_reason' => $event['status'] === BookingStatus::Cancelled ? 'Client requested cancellation.' : null,
            ]);

            $rateShare = (int) round(($event['total_cents'] * 0.6) / max($eventSpaces->count(), 1));
            foreach ($eventSpaces as $space) {
                BookingSpace::query()->create([
                    'booking_id' => $booking->id,
                    'space_id' => $space->id,
                    'start_at' => $start,
                    'end_at' => $end,
                    'setup_minutes_before' => 60,
                    'teardown_minutes_after' => 60,
                    'rate_applied_cents' => $rateShare,
                ]);
            }

            // dated note on tentative holds so the dashboard needs-attention
            // tile surfaces them as going cold (activity outside the staleness window)
            if ($event['status'] === BookingStatus::Tentative) {
                $booking->narratives()->create([
                    'author_user_id' => $owner?->id,
                    'kind' => 'note',
                    'body' => 'Tentative hold placed; awaiting signed contract from the client.',
                    'happened_at' => now()->subDays(random_int(18, 40)),
                ]);
            }

            $created++;
        }

        $this->command?->info("SentinelBayBookingsSeeder: created {$created} bookings.");
    }

    /**
     * Bookings across the five venues + every status, scaled relative to a
     * "now" of mid-2026 so completed events are recent and the pipeline runs into 2027.
     *
     * @return list<array{name:string,venue:string,spaces:list<string>,client:string,kind:string,status:BookingStatus,start:string,end:string,total_cents:int,attendance:int,deposit_percent?:int}>
     */
    protected function eventCalendar(): array
    {
        $pelican = 'pelican-cove-convention-center';
        $aquila = 'aquila-performing-arts-hall';
        $sports = 'sentinel-bay-sports-recreation-complex';
        $fairgrounds = 'driftwood-fairgrounds';
        $heron = 'heron-creek-retreat';

        return [
            // ---- Pelican Cove Convention Center ----
            [
                'name' => 'Sentinel Bay Naval Auxiliary Maritime Operations Symposium 2026',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sentinel Bay Naval Auxiliary',
                'kind' => 'conference', 'status' => BookingStatus::Completed,
                'start' => '2026-02-10 08:00', 'end' => '2026-02-12 17:00',
                'total_cents' => 3_850_000, 'attendance' => 650,
            ],
            [
                'name' => 'Sentinel Bay Boat & Lifestyle Show 2026',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Pelican Yacht Club',
                'kind' => 'trade_show', 'status' => BookingStatus::Completed,
                'start' => '2026-02-27 10:00', 'end' => '2026-03-01 18:00',
                'total_cents' => 4_125_000, 'attendance' => 3_200,
            ],
            [
                'name' => '10th Annual Sentinel Bay Health & Wellness Expo',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sandbar Inn & Spa',
                'kind' => 'expo', 'status' => BookingStatus::Definite,
                'start' => '2026-09-12 10:00', 'end' => '2026-09-13 17:00',
                'total_cents' => 1_950_000, 'attendance' => 900,
            ],
            [
                'name' => 'Multi-Chamber Business After Hours',
                'venue' => $pelican, 'spaces' => ['Bayfront Terrace'],
                'client' => 'Coastal Builders Co-op',
                'kind' => 'networking', 'status' => BookingStatus::Completed,
                'start' => '2026-04-09 17:30', 'end' => '2026-04-09 20:00',
                'total_cents' => 285_000, 'attendance' => 220,
            ],
            [
                'name' => 'Gulfwind Tech Summer Career Fair',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Gulfwind Technical Institute',
                'kind' => 'career_fair', 'status' => BookingStatus::Definite,
                'start' => '2026-07-09 10:00', 'end' => '2026-07-09 17:00',
                'total_cents' => 525_000, 'attendance' => 600,
            ],
            [
                'name' => 'Sentinel Bay Realtors Regional Conference',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sentinel Bay Realtors Association',
                'kind' => 'conference', 'status' => BookingStatus::Tentative,
                'start' => '2026-09-24 08:00', 'end' => '2026-09-26 17:00',
                'total_cents' => 2_850_000, 'attendance' => 550,
            ],
            [
                'name' => 'NAS Memorial Banquet',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Coral Reef NAS Officers Club',
                'kind' => 'banquet', 'status' => BookingStatus::Inquiry,
                'start' => '2026-11-14 17:00', 'end' => '2026-11-14 22:30',
                'total_cents' => 1_625_000, 'attendance' => 320,
            ],
            [
                'name' => 'Aquila State Career Expo - Spring',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Aquila State University',
                'kind' => 'career_fair', 'status' => BookingStatus::Definite,
                'start' => '2026-10-22 09:00', 'end' => '2026-10-22 16:00',
                'total_cents' => 615_000, 'attendance' => 800,
            ],
            [
                'name' => 'Coastal Construction Industry Awards Banquet',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Coastal Builders Co-op',
                'kind' => 'banquet', 'status' => BookingStatus::Definite,
                'start' => '2026-12-05 18:00', 'end' => '2026-12-05 23:00',
                'total_cents' => 1_475_000, 'attendance' => 280,
            ],
            [
                'name' => 'Sentinel Bay Schools Coordinator Workshop',
                'venue' => $pelican, 'spaces' => ['Tide 1', 'Tide 2'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'training', 'status' => BookingStatus::Definite,
                'start' => '2026-07-30 08:30', 'end' => '2026-07-30 16:30',
                'total_cents' => 165_000, 'attendance' => 85,
            ],
            [
                'name' => 'Sentinel Bay Boat & Lifestyle Show 2027 - venue hold',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Pelican Yacht Club',
                'kind' => 'trade_show', 'status' => BookingStatus::Hold,
                'start' => '2027-02-26 10:00', 'end' => '2027-02-28 18:00',
                'total_cents' => 4_300_000, 'attendance' => 3_200,
            ],
            // deposit_percent=0 unblocks the PaymentScheduleForm so the
            // installment-schedule path has a surface to drive against
            [
                'name' => 'Aquila State Annual Donor Summit 2027',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B'],
                'client' => 'Aquila State University',
                'kind' => 'conference', 'status' => BookingStatus::Definite,
                'start' => '2027-04-15 08:00', 'end' => '2027-04-17 17:00',
                'total_cents' => 2_400_000, 'attendance' => 450,
                'deposit_percent' => 0,
            ],

            // ---- Aquila Performing Arts Hall ----
            [
                'name' => 'First Methodist Christmas Cantata + Reception',
                'venue' => $aquila, 'spaces' => ['Main Theater', 'Pre-Function Lobby'],
                'client' => 'First Methodist Pelican Cove',
                'kind' => 'performance', 'status' => BookingStatus::Definite,
                'start' => '2026-12-18 18:00', 'end' => '2026-12-18 22:30',
                'total_cents' => 685_000, 'attendance' => 1_400,
            ],
            [
                'name' => "Mayor's State of the City Address",
                'venue' => $aquila, 'spaces' => ['Main Theater'],
                'client' => 'City of Pelican Cove',
                'kind' => 'civic', 'status' => BookingStatus::Definite,
                'start' => '2026-08-20 18:00', 'end' => '2026-08-20 20:30',
                'total_cents' => 425_000, 'attendance' => 1_200,
            ],
            [
                'name' => 'Driftwood Resort Owners Conference 2027',
                'venue' => $aquila, 'spaces' => ['Main Theater', 'Black Box Theater', 'Rehearsal Room 1'],
                'client' => 'Driftwood Resort Hotel',
                'kind' => 'conference', 'status' => BookingStatus::Tentative,
                'start' => '2027-03-04 08:00', 'end' => '2027-03-06 17:00',
                'total_cents' => 3_125_000, 'attendance' => 720,
            ],

            // ---- Sentinel Bay Sports & Recreation Complex ----
            [
                'name' => 'Aquila State Spring Hoops Tournament',
                'venue' => $sports, 'spaces' => ['Sentinel Bay Arena', 'Practice Gym'],
                'client' => 'Aquila State University',
                'kind' => 'sports', 'status' => BookingStatus::Completed,
                'start' => '2026-03-14 08:00', 'end' => '2026-03-15 21:00',
                'total_cents' => 1_485_000, 'attendance' => 4_200,
            ],
            [
                'name' => 'Sentinel Bay Schools Youth Cup',
                'venue' => $sports, 'spaces' => ['Tournament Field North', 'Tournament Field Center', 'Tournament Field South'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-10-10 08:00', 'end' => '2026-10-11 18:00',
                'total_cents' => 1_150_000, 'attendance' => 1_800,
            ],
            [
                'name' => 'Workforce Connect Job Fair',
                'venue' => $sports, 'spaces' => ['Sentinel Bay Arena'],
                'client' => 'Sentinel Bay Workforce Development Board',
                'kind' => 'career_fair', 'status' => BookingStatus::Tentative,
                'start' => '2026-11-06 09:00', 'end' => '2026-11-06 16:00',
                'total_cents' => 695_000, 'attendance' => 1_500,
            ],
            [
                'name' => 'Tournament VIP Reception',
                'venue' => $sports, 'spaces' => ['Hospitality Suite A'],
                'client' => 'Aquila State University',
                'kind' => 'reception', 'status' => BookingStatus::Definite,
                'start' => '2026-10-10 18:00', 'end' => '2026-10-10 21:30',
                'total_cents' => 145_000, 'attendance' => 32,
            ],

            // ---- Driftwood Fairgrounds ----
            [
                'name' => 'Florida Cattlemen Spring Convention',
                'venue' => $fairgrounds, 'spaces' => ['Indoor Expo Hall'],
                'client' => 'Coral Cattlemen Association',
                'kind' => 'conference', 'status' => BookingStatus::Completed,
                'start' => '2026-03-21 09:00', 'end' => '2026-03-22 17:00',
                'total_cents' => 1_650_000, 'attendance' => 2_200,
            ],
            [
                'name' => 'Sentinel Bay Junior Rodeo Finals',
                'venue' => $fairgrounds, 'spaces' => ['Livestock Arena', 'Stall Block'],
                'client' => 'Sentinel Bay Junior Rodeo',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-06-20 09:00', 'end' => '2026-06-22 21:00',
                'total_cents' => 1_850_000, 'attendance' => 1_200,
            ],
            [
                'name' => 'Sentinel Bay Brewfest 2026',
                'venue' => $fairgrounds, 'spaces' => ['Outdoor Festival Grounds'],
                'client' => 'Sentinel Bay Brewfest LLC',
                'kind' => 'festival', 'status' => BookingStatus::Hold,
                'start' => '2026-09-19 14:00', 'end' => '2026-09-19 22:00',
                'total_cents' => 1_900_000, 'attendance' => 2_500,
            ],
            [
                'name' => 'Annual Sentinel Bay County Fair',
                'venue' => $fairgrounds, 'spaces' => ['Outdoor Festival Grounds', 'Indoor Expo Hall'],
                'client' => 'Coral Cattlemen Association',
                'kind' => 'festival', 'status' => BookingStatus::Definite,
                'start' => '2026-10-22 10:00', 'end' => '2026-10-31 22:00',
                'total_cents' => 8_500_000, 'attendance' => 25_000,
            ],
            [
                'name' => 'Tidewater Bay Casino Industry Showcase',
                'venue' => $fairgrounds, 'spaces' => ['Indoor Expo Hall'],
                'client' => 'Tidewater Bay Casino',
                'kind' => 'trade_show', 'status' => BookingStatus::Definite,
                'start' => '2026-08-08 10:00', 'end' => '2026-08-09 18:00',
                'total_cents' => 1_275_000, 'attendance' => 1_100,
            ],

            // ---- Heron Creek Retreat ----
            [
                'name' => 'Johnson-Reyes Wedding',
                'venue' => $heron, 'spaces' => ['Lakeside Pavilion'],
                'client' => 'Johnson-Reyes Wedding',
                'kind' => 'wedding', 'status' => BookingStatus::Definite,
                'start' => '2026-06-13 15:00', 'end' => '2026-06-13 23:30',
                'total_cents' => 1_650_000, 'attendance' => 180,
            ],
            [
                'name' => 'Patel Sangeet',
                'venue' => $heron, 'spaces' => ['Open-Pole Barn'],
                'client' => 'Patel Sangeet',
                'kind' => 'celebration', 'status' => BookingStatus::Definite,
                'start' => '2026-10-03 17:00', 'end' => '2026-10-03 23:30',
                'total_cents' => 925_000, 'attendance' => 220,
            ],
            [
                'name' => 'First Methodist Family Retreat',
                'venue' => $heron, 'spaces' => ['Ceremony Lawn', 'Heron Cabin 1', 'Heron Cabin 2'],
                'client' => 'First Methodist Pelican Cove',
                'kind' => 'retreat', 'status' => BookingStatus::Completed,
                'start' => '2026-05-02 09:00', 'end' => '2026-05-03 16:00',
                'total_cents' => 425_000, 'attendance' => 95,
            ],
            [
                'name' => 'Marlin Bay Conservancy Gala',
                'venue' => $heron, 'spaces' => ['Lakeside Pavilion'],
                'client' => 'Marlin Bay Conservancy',
                'kind' => 'fundraiser', 'status' => BookingStatus::Tentative,
                'start' => '2026-11-07 18:00', 'end' => '2026-11-07 23:00',
                'total_cents' => 1_125_000, 'attendance' => 175,
            ],

            // ---- Density: late May + June 2026 ----
            // fills the current-and-next-month window so the calendar /
            // schedule / ops board read like a working portfolio

            // May 2026 - recent / current
            [
                'name' => 'City Council Public Forum',
                'venue' => $aquila, 'spaces' => ['Black Box Theater'],
                'client' => 'City of Pelican Cove',
                'kind' => 'civic', 'status' => BookingStatus::Completed,
                'start' => '2026-05-14 18:00', 'end' => '2026-05-14 20:30',
                'total_cents' => 145_000, 'attendance' => 220,
            ],
            [
                'name' => 'Aquila State Spring Convocation',
                'venue' => $aquila, 'spaces' => ['Main Theater'],
                'client' => 'Aquila State University',
                'kind' => 'civic', 'status' => BookingStatus::Completed,
                'start' => '2026-05-16 14:00', 'end' => '2026-05-16 17:00',
                'total_cents' => 320_000, 'attendance' => 1_200,
            ],
            [
                'name' => 'Coastal Builders Procurement Roundtable',
                'venue' => $pelican, 'spaces' => ['Tide 1', 'Tide 2'],
                'client' => 'Coastal Builders Co-op',
                'kind' => 'training', 'status' => BookingStatus::Completed,
                'start' => '2026-05-19 09:00', 'end' => '2026-05-19 15:00',
                'total_cents' => 165_000, 'attendance' => 48,
            ],
            [
                'name' => 'Sentinel Bay HR Compliance Workshop',
                'venue' => $pelican, 'spaces' => ['Sunrise 1', 'Sunrise 2'],
                'client' => 'Sentinel Bay Workforce Development Board',
                'kind' => 'training', 'status' => BookingStatus::Completed,
                'start' => '2026-05-21 08:30', 'end' => '2026-05-21 16:30',
                'total_cents' => 195_000, 'attendance' => 60,
            ],
            [
                'name' => 'High School Class of 2026 Graduation Ceremony',
                'venue' => $aquila, 'spaces' => ['Main Theater'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'civic', 'status' => BookingStatus::Completed,
                'start' => '2026-05-23 18:00', 'end' => '2026-05-23 21:00',
                'total_cents' => 285_000, 'attendance' => 1_650,
            ],
            [
                'name' => 'Air Station Family Day',
                'venue' => $sports, 'spaces' => ['Sentinel Bay Arena', 'Practice Gym'],
                'client' => 'Sentinel Bay Naval Auxiliary',
                'kind' => 'reception', 'status' => BookingStatus::Completed,
                'start' => '2026-05-24 10:00', 'end' => '2026-05-24 18:00',
                'total_cents' => 780_000, 'attendance' => 2_100,
            ],
            [
                'name' => 'Memorial Day Sunset Concert',
                'venue' => $pelican, 'spaces' => ['Bayfront Lawn'],
                'client' => 'City of Pelican Cove',
                'kind' => 'concert', 'status' => BookingStatus::Completed,
                'start' => '2026-05-25 17:00', 'end' => '2026-05-25 22:00',
                'total_cents' => 425_000, 'attendance' => 1_400,
            ],
            [
                'name' => 'Bridal Affair Sentinel Bay - Spring Showcase',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sunny Sands Wedding Co',
                'kind' => 'expo', 'status' => BookingStatus::Completed,
                'start' => '2026-05-28 11:00', 'end' => '2026-05-28 18:00',
                'total_cents' => 985_000, 'attendance' => 720,
            ],
            [
                'name' => 'Marlin Bay Conservancy Volunteer Day',
                'venue' => $heron, 'spaces' => ['Ceremony Lawn'],
                'client' => 'Marlin Bay Conservancy',
                'kind' => 'community', 'status' => BookingStatus::Definite,
                'start' => '2026-05-30 08:00', 'end' => '2026-05-30 15:00',
                'total_cents' => 95_000, 'attendance' => 80,
            ],
            [
                'name' => 'Coastal Cattle Pre-Sale',
                'venue' => $fairgrounds, 'spaces' => ['Livestock Arena', 'Stall Block'],
                'client' => 'Coral Cattlemen Association',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-05-31 09:00', 'end' => '2026-05-31 17:00',
                'total_cents' => 285_000, 'attendance' => 320,
            ],

            // June 2026 - next month
            [
                'name' => 'State Realtors Code-of-Ethics CE',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sentinel Bay Realtors Association',
                'kind' => 'training', 'status' => BookingStatus::Definite,
                'start' => '2026-06-03 08:30', 'end' => '2026-06-03 16:00',
                'total_cents' => 525_000, 'attendance' => 280,
            ],
            [
                'name' => 'Workforce Connect Kickoff Breakfast',
                'venue' => $pelican, 'spaces' => ['Bayfront Terrace'],
                'client' => 'Sentinel Bay Workforce Development Board',
                'kind' => 'networking', 'status' => BookingStatus::Definite,
                'start' => '2026-06-05 07:30', 'end' => '2026-06-05 10:00',
                'total_cents' => 165_000, 'attendance' => 140,
            ],
            [
                'name' => 'Sentinel Bay Junior Rodeo Clinic',
                'venue' => $fairgrounds, 'spaces' => ['Livestock Arena'],
                'client' => 'Sentinel Bay Junior Rodeo',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-06-06 09:00', 'end' => '2026-06-07 17:00',
                'total_cents' => 385_000, 'attendance' => 220,
            ],
            [
                'name' => 'Tidewater Bay Casino Charity Golf Awards',
                'venue' => $sports, 'spaces' => ['Hospitality Suite A'],
                'client' => 'Tidewater Bay Casino',
                'kind' => 'banquet', 'status' => BookingStatus::Definite,
                'start' => '2026-06-06 18:00', 'end' => '2026-06-06 22:30',
                'total_cents' => 215_000, 'attendance' => 36,
            ],
            [
                'name' => 'Schools Youth Soccer Camp',
                'venue' => $sports, 'spaces' => ['Tournament Field North'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-06-08 08:00', 'end' => '2026-06-10 17:00',
                'total_cents' => 425_000, 'attendance' => 360,
            ],
            [
                'name' => 'Sentinel Bay County Bar CLE',
                'venue' => $pelican, 'spaces' => ['Sunrise 1', 'Sunrise 2'],
                'client' => 'Sentinel Bay Workforce Development Board',
                'kind' => 'training', 'status' => BookingStatus::Definite,
                'start' => '2026-06-11 08:30', 'end' => '2026-06-11 16:30',
                'total_cents' => 185_000, 'attendance' => 55,
            ],
            [
                'name' => 'Builders RV Rally Weekend',
                'venue' => $fairgrounds, 'spaces' => ['RV Camping', 'Concession + Multi-Purpose Hall'],
                'client' => 'Coastal Builders Co-op',
                'kind' => 'festival', 'status' => BookingStatus::Definite,
                'start' => '2026-06-12 14:00', 'end' => '2026-06-14 16:00',
                'total_cents' => 545_000, 'attendance' => 280,
            ],
            [
                'name' => 'Sentinel Bay Symphony Pops Concert',
                'venue' => $aquila, 'spaces' => ['Main Theater'],
                'client' => 'City of Pelican Cove',
                'kind' => 'concert', 'status' => BookingStatus::Definite,
                'start' => '2026-06-13 19:30', 'end' => '2026-06-13 22:00',
                'total_cents' => 385_000, 'attendance' => 1_600,
            ],
            [
                'name' => 'Methodist Youth Retreat',
                'venue' => $heron, 'spaces' => ['Open-Pole Barn', 'Heron Cabin 3'],
                'client' => 'First Methodist Pelican Cove',
                'kind' => 'retreat', 'status' => BookingStatus::Definite,
                'start' => '2026-06-13 09:00', 'end' => '2026-06-14 16:00',
                'total_cents' => 285_000, 'attendance' => 110,
            ],
            [
                'name' => 'Coral Cattlemen Summer BBQ',
                'venue' => $sports, 'spaces' => ['Hospitality Suite B'],
                'client' => 'Coral Cattlemen Association',
                'kind' => 'reception', 'status' => BookingStatus::Definite,
                'start' => '2026-06-14 17:00', 'end' => '2026-06-14 22:00',
                'total_cents' => 125_000, 'attendance' => 38,
            ],
            [
                'name' => 'Aquila State Donor Reception',
                'venue' => $pelican, 'spaces' => ['Bayfront Terrace'],
                'client' => 'Aquila State University',
                'kind' => 'reception', 'status' => BookingStatus::Definite,
                'start' => '2026-06-17 18:00', 'end' => '2026-06-17 21:30',
                'total_cents' => 235_000, 'attendance' => 95,
            ],
            [
                'name' => 'Yoga Retreat Weekend',
                'venue' => $heron, 'spaces' => ['Lakeside Pavilion'],
                'client' => 'Sunny Sands Wedding Co',
                'kind' => 'retreat', 'status' => BookingStatus::Definite,
                'start' => '2026-06-19 09:00', 'end' => '2026-06-21 16:00',
                'total_cents' => 425_000, 'attendance' => 60,
            ],
            [
                'name' => "Children's Theater Workshop Showcase",
                'venue' => $aquila, 'spaces' => ['Black Box Theater'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'performance', 'status' => BookingStatus::Definite,
                'start' => '2026-06-21 14:00', 'end' => '2026-06-21 17:00',
                'total_cents' => 95_000, 'attendance' => 240,
            ],
            [
                'name' => 'Summer Hoops Camp',
                'venue' => $sports, 'spaces' => ['Practice Gym'],
                'client' => 'Aquila State University',
                'kind' => 'sports', 'status' => BookingStatus::Definite,
                'start' => '2026-06-22 08:00', 'end' => '2026-06-26 16:00',
                'total_cents' => 725_000, 'attendance' => 120,
            ],
            [
                'name' => 'Hospitality Showcase 2026',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B', 'Coral Reef Grand Ballroom C', 'Coral Reef Grand Ballroom D'],
                'client' => 'Sentinel Bay Beachfront Hotel',
                'kind' => 'trade_show', 'status' => BookingStatus::Definite,
                'start' => '2026-06-24 10:00', 'end' => '2026-06-25 17:00',
                'total_cents' => 1_650_000, 'attendance' => 850,
            ],
            [
                'name' => 'Naval Auxiliary Charity Golf Auction Dinner',
                'venue' => $pelican, 'spaces' => ['Coral Reef Grand Ballroom + Exhibit Hall A', 'Coral Reef Grand Ballroom B'],
                'client' => 'Sentinel Bay Naval Auxiliary',
                'kind' => 'banquet', 'status' => BookingStatus::Tentative,
                'start' => '2026-06-27 18:00', 'end' => '2026-06-27 23:00',
                'total_cents' => 685_000, 'attendance' => 240,
            ],
            [
                'name' => 'Pelican Yacht Club Junior Regatta Awards',
                'venue' => $pelican, 'spaces' => ['Bayfront Terrace'],
                'client' => 'Pelican Yacht Club',
                'kind' => 'banquet', 'status' => BookingStatus::Tentative,
                'start' => '2026-06-28 17:30', 'end' => '2026-06-28 21:00',
                'total_cents' => 165_000, 'attendance' => 110,
            ],

            // ---- One cancellation to exercise the status ----
            [
                'name' => 'Spring Workshop (cancelled - venue conflict)',
                'venue' => $pelican, 'spaces' => ['Tide 1', 'Tide 2'],
                'client' => 'Sentinel Bay County Schools District',
                'kind' => 'training', 'status' => BookingStatus::Cancelled,
                'start' => '2026-03-14 10:00', 'end' => '2026-03-14 16:00',
                'total_cents' => 145_000, 'attendance' => 200,
            ],
        ];
    }

    /**
     * @param  Collection<string, Venue>  $venues
     */
    protected function seedRateCards($venues): void
    {
        foreach ($venues as $venue) {
            $rateCard = RateCard::query()->firstOrCreate(
                ['venue_id' => $venue->id, 'name' => 'Standard 2026'],
                [
                    'kind' => RateCardKind::Standard->value,
                    'currency' => 'USD',
                    'effective_from' => '2026-01-01',
                    'is_active' => true,
                ],
            );

            foreach ($venue->spaces as $space) {
                RateCardEntry::query()->firstOrCreate(
                    ['rate_card_id' => $rateCard->id, 'space_id' => $space->id],
                    [
                        'unit' => BookableUnit::Daily->value,
                        'rate_cents' => $this->rateFor($space),
                        'min_charge_cents' => 0,
                    ],
                );
            }
        }
    }

    protected function rateFor(Space $space): int
    {
        $sqft = $space->sqft ?? 1000;

        return match (true) {
            $sqft > 10_000 => 350_000,
            $sqft > 2_000 => 150_000,
            $sqft > 500 => 60_000,
            default => 25_000,
        };
    }
}
