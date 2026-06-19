<?php

namespace Database\Seeders;

use App\Enums\ActivityKind;
use App\Enums\ClientType;
use App\Enums\LeadStage;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Demo sales pipeline: ~22 clients across all five client types, leads in
 * every stage, 1-3 activities per open lead. Lead ownership is spread
 * across the Sales team rather than pinned to the first user.
 *
 * Idempotent: skips if 10+ clients already exist.
 */
class SentinelBaySalesSeeder extends Seeder
{
    public function run(): void
    {
        if (Client::query()->count() >= 10) {
            $this->command?->info('SentinelBaySalesSeeder: existing clients present, skipping.');

            return;
        }

        $venues = Venue::query()->active()->get();
        if ($venues->isEmpty()) {
            $this->command?->warn('SentinelBaySalesSeeder: no active venues. Run SentinelBayVenuesSeeder first.');

            return;
        }

        // distribute lead ownership so each owner has a few leads
        $salesEmails = [
            'rachel.tate@sentinelbay.ca.gov',
            'david.kim@sentinelbay.ca.gov',
            'aaliyah.brooks@sentinelbay.ca.gov',
        ];
        $salesOwners = User::query()->whereIn('email', $salesEmails)->get();

        if ($salesOwners->isEmpty()) {
            $salesOwners = collect([
                User::query()->orderBy('id')->first()
                    ?? User::factory()->create(['name' => 'Sentinel Bay Sales', 'email' => 'sales@sentinelbay.ca.gov']),
            ]);
        }

        $clientRoster = [
            // --- Hotels (booking ballrooms for industry events) ---
            ['Sandbar Inn & Spa', ClientType::Business, 'Hospitality'],
            ['Driftwood Resort Hotel', ClientType::Business, 'Hospitality'],
            ['Sentinel Bay Beachfront Hotel', ClientType::Business, 'Hospitality'],

            // --- Casino / gaming ---
            ['Tidewater Bay Casino', ClientType::Business, 'Gaming & Entertainment'],

            // --- Universities ---
            ['Aquila State University', ClientType::Educational, 'Education'],
            ['Gulfwind Technical Institute', ClientType::Educational, 'Education'],

            // --- Military ---
            ['Sentinel Bay Naval Auxiliary', ClientType::Government, 'Military'],
            ['Coral Reef NAS Officers Club', ClientType::Government, 'Military'],

            // --- Government / Civic ---
            ['Sentinel Bay County Schools District', ClientType::Government, 'Education'],
            ['Sentinel Bay Workforce Development Board', ClientType::Government, 'Workforce'],
            ['City of Pelican Cove', ClientType::Government, 'Civic'],

            // --- Industry / trade associations ---
            ['Sentinel Bay Realtors Association', ClientType::Nonprofit, 'Real Estate'],
            ['Coastal Builders Co-op', ClientType::Business, 'Construction'],
            ['Coral Cattlemen Association', ClientType::Nonprofit, 'Agriculture'],
            ['Pelican Yacht Club', ClientType::Nonprofit, 'Recreation'],
            ['Sentinel Bay Brewfest LLC', ClientType::Business, 'Tourism'],

            // --- Community / non-profit ---
            ['First Methodist Pelican Cove', ClientType::Nonprofit, 'Religious'],
            ['Marlin Bay Conservancy', ClientType::Nonprofit, 'Environment'],
            ['Sentinel Bay Junior Rodeo', ClientType::Nonprofit, 'Sports'],
            ['Sunny Sands Wedding Co', ClientType::Business, 'Hospitality'],

            // --- Individuals ---
            ['Johnson-Reyes Wedding', ClientType::Individual, null],
            ['Patel Sangeet', ClientType::Individual, null],
        ];

        $stageWeights = [
            LeadStage::New, LeadStage::New,
            LeadStage::Qualified, LeadStage::Qualified, LeadStage::Qualified,
            LeadStage::ProposalSent, LeadStage::ProposalSent,
            LeadStage::ContractSent,
            LeadStage::Won,
            LeadStage::Lost,
        ];

        foreach ($clientRoster as $idx => [$name, $type, $industry]) {
            $client = Client::query()->create([
                'name' => $name,
                'type' => $type->value,
                'industry' => $industry,
                'source' => collect(['referral', 'website', 'event', 'cold_outreach', 'partner'])->random(),
                'address_json' => ['city' => 'Pelican Cove', 'state' => 'FL'],
                'notes' => null,
            ]);

            $contact = Contact::query()->create([
                'client_id' => $client->id,
                'name' => $type === ClientType::Individual ? $name : 'Coordinator at '.$name,
                'role' => $type === ClientType::Individual ? null : 'Event Coordinator',
                'email' => 'contact'.($idx + 1).'@example.test',
                'phone' => '850-'.random_int(200, 999).'-'.random_int(1000, 9999),
                'is_primary' => true,
            ]);

            $client->update(['primary_contact_id' => $contact->id]);

            $stage = $stageWeights[$idx % count($stageWeights)];
            $owner = $salesOwners[$idx % $salesOwners->count()];

            $lead = Lead::query()->create([
                'client_id' => $client->id,
                'venue_id' => $venues->random()->id,
                'owner_user_id' => $owner->id,
                'name' => $this->leadNameFor($type, $name),
                'stage' => $stage->value,
                'estimated_value_cents' => random_int(8_000_00, 250_000_00),
                'probability' => $stage->defaultProbability(),
                'expected_close_date' => now()->addDays(random_int(-30, 120))->toDateString(),
                'source' => $client->source,
                'lost_reason' => $stage === LeadStage::Lost
                    ? collect(['budget', 'timing', 'competition', 'fit'])->random()
                    : null,
            ]);

            if ($stage->isOpen()) {
                $activityCount = random_int(1, 3);
                $activityKinds = [ActivityKind::Call, ActivityKind::Email, ActivityKind::Meeting, ActivityKind::Task, ActivityKind::SiteVisit];

                foreach (range(1, $activityCount) as $i) {
                    $kind = $activityKinds[array_rand($activityKinds)];
                    $dueAt = now()->addDays(random_int(-7, 14));
                    $isCompleted = $dueAt->isPast() && random_int(0, 1) === 1;

                    Activity::query()->create([
                        'subject_type' => Lead::class,
                        'subject_id' => $lead->id,
                        'user_id' => $owner->id,
                        'kind' => $kind->value,
                        'summary' => $this->activitySummaryFor($kind),
                        'due_at' => $dueAt,
                        'completed_at' => $isCompleted ? $dueAt : null,
                    ]);
                }
            }
        }

        // park a couple contract-sent leads in the stuck window so the
        // needs-attention tile has something to surface; Postgres can't
        // UPDATE ... LIMIT, so collect the ids first
        $stuckLeadIds = Lead::query()
            ->where('stage', LeadStage::ContractSent->value)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');
        Lead::query()
            ->whereIn('id', $stuckLeadIds)
            ->update(['updated_at' => now()->subDays(21)]);

        $this->command?->info('SentinelBaySalesSeeder: created '.count($clientRoster).' clients with leads + activities.');
    }

    protected function leadNameFor(ClientType $type, string $clientName): string
    {
        $monthOut = now()->addMonths(random_int(2, 14))->format('M Y');

        if ($type === ClientType::Individual) {
            $occasion = str_contains(strtolower($clientName), 'sangeet') ? 'Sangeet' : 'Wedding';

            return $occasion.' - '.$monthOut;
        }

        $themes = [
            'Sandbar Inn & Spa' => 'Hospitality Industry Awards Banquet',
            'Driftwood Resort Hotel' => 'Resort Owners Conference 2027',
            'Sentinel Bay Beachfront Hotel' => 'Travel & Tourism Summit',
            'Tidewater Bay Casino' => 'High-Roller Hospitality Showcase',
            'Aquila State University' => 'ASU Career Expo - Spring',
            'Gulfwind Technical Institute' => 'Gulfwind Tech Skills Showcase',
            'Sentinel Bay Naval Auxiliary' => 'Naval Auxiliary Family Day Banquet',
            'Coral Reef NAS Officers Club' => 'NAS Officers Memorial Dinner',
            'Sentinel Bay County Schools District' => 'District Teacher Inservice Day',
            'Sentinel Bay Workforce Development Board' => 'Workforce Connect Job Fair',
            'City of Pelican Cove' => "Mayor's State of the City Address",
            'Sentinel Bay Realtors Association' => 'Sentinel Bay Realtor Regional Conference',
            'Coastal Builders Co-op' => 'Coastal Construction Industry Awards',
            'Coral Cattlemen Association' => 'Florida Cattlemen Spring Convention',
            'Pelican Yacht Club' => 'Member Regatta Awards Banquet',
            'Sentinel Bay Brewfest LLC' => 'Sentinel Bay Brewfest - Fall edition',
            'First Methodist Pelican Cove' => 'Christmas Cantata + Reception',
            'Marlin Bay Conservancy' => 'Conservation Gala 2027',
            'Sentinel Bay Junior Rodeo' => 'Junior Rodeo Finals 2027',
            'Sunny Sands Wedding Co' => 'Bridal Affair Sentinel Bay - spring showcase',
        ];

        $base = $themes[$clientName] ?? 'Annual Banquet';

        return $base.' - '.$monthOut;
    }

    protected function activitySummaryFor(ActivityKind $kind): string
    {
        return match ($kind) {
            ActivityKind::Call => 'Discovery call',
            ActivityKind::Email => 'Send proposal',
            ActivityKind::Meeting => 'On-site walk-through',
            ActivityKind::SiteVisit => 'Tour Coral Reef Ballroom',
            ActivityKind::Task => 'Confirm date availability',
            ActivityKind::Note => 'Notes from initial discussion',
        };
    }
}
