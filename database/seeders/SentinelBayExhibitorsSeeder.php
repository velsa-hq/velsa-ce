<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ExhibitorOrderStatus;
use App\Enums\ExhibitorPermitStatus;
use App\Enums\ExhibitorPermitType;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\ExhibitorPayment;
use App\Models\ExhibitorPermit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds exhibitor halls on upcoming trade-show / expo bookings, up to
 * three events so the admin and back-office dashboards have more than a
 * single hall.
 *
 * Idempotent: skips when any exhibitor_event already exists.
 */
class SentinelBayExhibitorsSeeder extends Seeder
{
    public function run(): void
    {
        if (ExhibitorEvent::query()->count() > 0) {
            $this->command?->info('SentinelBayExhibitorsSeeder: exhibitor events already exist, skipping.');

            return;
        }

        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Definite->value, BookingStatus::Tentative->value])
            ->whereIn('kind', ['expo', 'trade_show'])
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->take(3)
            ->get();

        if ($bookings->isEmpty()) {
            $this->command?->warn('SentinelBayExhibitorsSeeder: no upcoming expo/trade-show bookings. Run SentinelBayBookingsSeeder first.');

            return;
        }

        $companyPool = [
            'Sentinel Bay Outdoor Adventures', 'Coastal Brewery Co', 'Sunshine Coast Solar',
            'Pelican Pet Supplies', 'Driftwood Realty Group', 'Sentinel Bay Catering Co',
            'Pacific Coast Marine', 'Apex Audio Visual', 'Backwoods BBQ Supply',
            'Pelican Photography', 'Coral Boutique', 'Lighthouse Roasters',
            'Sea Salt Spa', 'Coastal Travel Co', 'Wild Florida Tours',
            'Marlin Sports Outfitters', 'Driftwood Wine Co', 'Bayfront Fitness',
        ];

        $itemCatalog = [
            ['10x10 Booth', 'BOOTH-10', 75_000, 'booths'],
            ['10x20 Booth', 'BOOTH-20', 130_000, 'booths'],
            ['Electricity (15A)', 'ELEC-15', 15_000, 'utilities'],
            ['Wifi Access', 'WIFI', 5_000, 'utilities'],
            ['6 Foot Table', 'TBL-6', 2_500, 'furniture'],
            ['Chairs (4-pack)', 'CHR-4', 3_500, 'furniture'],
            ['Hanging Sign', 'SIGN', 25_000, 'av'],
        ];

        $orderStatusBuckets = [
            ExhibitorOrderStatus::Cart,
            ExhibitorOrderStatus::Pending, ExhibitorOrderStatus::Pending,
            ExhibitorOrderStatus::PartiallyPaid,
            ExhibitorOrderStatus::Paid, ExhibitorOrderStatus::Paid, ExhibitorOrderStatus::Paid,
            ExhibitorOrderStatus::Paid, ExhibitorOrderStatus::Paid,
            ExhibitorOrderStatus::Cancelled,
        ];

        // permit requests keyed off the exhibitor index; most slots null,
        // the rest seed pending plus a couple decided examples
        // [type, status, details, review_notes]
        $permitBuckets = [
            [ExhibitorPermitType::FoodSampling, ExhibitorPermitStatus::Pending, 'Sampling cold-brew coffee, 2 oz cups, ~300 servings across show hours.', null],
            null,
            [ExhibitorPermitType::OpenFlame, ExhibitorPermitStatus::Pending, 'Live cooking demo with a single propane burner at the booth, 11am-2pm daily.', null],
            null,
            [ExhibitorPermitType::VehicleMoveIn, ExhibitorPermitStatus::Approved, 'Drive a display boat onto the floor during move-in Tuesday 6am.', 'Approved - escort required, floor protection mats down before entry.'],
            null,
            [ExhibitorPermitType::AmplifiedSound, ExhibitorPermitStatus::Denied, 'Continuous amplified product video at booth speakers.', 'Denied - exceeds hall sound policy; ambient-level audio only.'],
            null,
        ];

        $totalExhibitors = 0;
        $companies = collect($companyPool)->shuffle()->values();
        $companyCursor = 0;

        foreach ($bookings as $bookingIdx => $booking) {
            // first event gets the most exhibitors, fall off after
            $count = [12, 8, 6][$bookingIdx] ?? 6;
            $eventCompanies = $companies->slice($companyCursor, $count)->values();
            $companyCursor += $count;

            // wrap the cursor if we ran past the pool
            if ($eventCompanies->count() < $count) {
                $needed = $count - $eventCompanies->count();
                $companyCursor = $needed;
                $eventCompanies = $eventCompanies->concat($companies->slice(0, $needed))->values();
            }

            $event = ExhibitorEvent::query()->create([
                'booking_id' => $booking->id,
                'name' => $booking->name.' - Exhibitor Hall',
                'portal_slug' => Str::slug($booking->name).'-'.now()->year.'-'.$booking->id,
                'default_booth_size' => '10x10',
                'registration_opens_at' => now()->subDays(60),
                'registration_closes_at' => $booking->start_at->copy()->subDays(7),
            ]);

            foreach ($eventCompanies as $idx => $company) {
                $boothNumber = sprintf('%03d', 101 + $idx);

                $exhibitor = Exhibitor::query()->create([
                    'exhibitor_event_id' => $event->id,
                    'company_name' => $company,
                    'contact_name' => 'Coordinator at '.$company,
                    'email' => 'booth-'.$event->id.'-'.($idx + 1).'@example.test',
                    'phone' => '850-'.random_int(200, 999).'-'.random_int(1000, 9999),
                    'booth_assignment' => $boothNumber,
                    'booth_size' => $idx % 5 === 0 ? '10x20' : '10x10',
                ]);

                $orderStatus = $orderStatusBuckets[$idx % count($orderStatusBuckets)];

                $order = ExhibitorOrder::query()->create([
                    'exhibitor_id' => $exhibitor->id,
                    'status' => $orderStatus->value,
                    'placed_at' => $orderStatus === ExhibitorOrderStatus::Cart ? null : now()->subDays(random_int(1, 30)),
                ]);

                $itemCount = random_int(2, 4);
                $picked = collect($itemCatalog)->shuffle()->take($itemCount);
                foreach ($picked as $row) {
                    ExhibitorOrderItem::query()->create([
                        'exhibitor_order_id' => $order->id,
                        'sku' => $row[1],
                        'name' => $row[0],
                        'department' => $row[3],
                        'gl_account' => '4'.random_int(100, 999),
                        'quantity' => $row[3] === 'booths' ? 1 : random_int(1, 4),
                        'unit_price_cents' => $row[2],
                        'line_total_cents' => 0,
                    ]);
                }

                $order->refresh();
                $order->recalculateTotals();

                if ($orderStatus === ExhibitorOrderStatus::Paid) {
                    ExhibitorPayment::query()->create([
                        'exhibitor_order_id' => $order->id,
                        'provider' => 'bluepay',
                        'provider_transaction_id' => 'bp_seed_'.$order->id,
                        'status' => PaymentStatus::Captured->value,
                        'amount_cents' => $order->total_cents,
                        'last4' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                        'card_brand' => collect(['visa', 'mastercard', 'amex'])->random(),
                        'idempotency_key' => 'seed-'.$order->id.'-'.Str::random(8),
                        'processed_at' => $order->placed_at?->addHours(2) ?? now(),
                    ]);
                    $order->update(['paid_cents' => $order->total_cents]);
                } elseif ($orderStatus === ExhibitorOrderStatus::PartiallyPaid) {
                    $deposit = (int) round($order->total_cents / 2);
                    ExhibitorPayment::query()->create([
                        'exhibitor_order_id' => $order->id,
                        'provider' => 'bluepay',
                        'provider_transaction_id' => 'bp_seed_'.$order->id,
                        'status' => PaymentStatus::Captured->value,
                        'amount_cents' => $deposit,
                        'last4' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                        'card_brand' => 'visa',
                        'idempotency_key' => 'seed-'.$order->id.'-'.Str::random(8),
                        'processed_at' => $order->placed_at?->addHours(2) ?? now(),
                    ]);
                    $order->update(['paid_cents' => $deposit]);
                }

                // a few permit requests so the review queue has pending work
                // plus a couple decided examples
                $permitSpec = $permitBuckets[$idx % count($permitBuckets)];
                if ($permitSpec !== null) {
                    [$type, $status, $details, $note] = $permitSpec;
                    ExhibitorPermit::query()->create([
                        'exhibitor_id' => $exhibitor->id,
                        'permit_type' => $type->value,
                        'details' => $details,
                        'status' => $status->value,
                        'review_notes' => $note,
                        'reviewed_at' => $status === ExhibitorPermitStatus::Pending ? null : now()->subDays(random_int(1, 10)),
                        'submitted_via_portal' => true,
                    ]);
                }

                $totalExhibitors++;
            }
        }

        $this->command?->info("SentinelBayExhibitorsSeeder: created {$bookings->count()} exhibitor events with {$totalExhibitors} exhibitors.");
    }
}
