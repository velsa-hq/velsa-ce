<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Writes a booking and its cross-aggregate side effects as one transaction:
 * an inline new-client (+ primary contact) upsert, the booked-space fan-out,
 * and the once-only lead-conversion stamp. Keeps that orchestration out of the
 * controller so it's testable and reusable.
 *
 * The BookingSpace overlap guard throws RuntimeException; this service does not
 * catch it - the caller maps it to a `spaces` form error.
 *
 * Note: the SPACE_BUFFER_MINUTES default is the staff-UI convention. The CSV
 * BookingImporter deliberately creates spaces with no buffer (the migration
 * default of 0) and does NOT route through here - do not "reuse" this writer
 * for imports without deciding that buffer change explicitly.
 */
class BookingWriter
{
    /** Default setup/teardown buffer (minutes) for a space booked via the UI. */
    private const SPACE_BUFFER_MINUTES = 60;

    /**
     * @param  array<string, mixed>  $data  validated BookingStoreRequest data
     */
    public function create(array $data, User $owner): Booking
    {
        $totalCents = isset($data['total_dollars']) ? Money::toCents($data['total_dollars']) : 0;

        return DB::transaction(function () use ($data, $owner, $totalCents) {
            $clientId = $data['client_id'] ?? null;

            if ($clientId === null && ! empty($data['new_client']['name'])) {
                $newClient = $data['new_client'];
                $client = Client::query()->create([
                    'name' => $newClient['name'],
                    'type' => $newClient['type'],
                ]);

                if (! empty($newClient['email'])) {
                    $contact = Contact::query()->create([
                        'client_id' => $client->id,
                        'name' => $newClient['name'],
                        'email' => $newClient['email'],
                        'is_primary' => true,
                    ]);
                    $client->update(['primary_contact_id' => $contact->id]);
                }

                $clientId = $client->id;
            }

            $booking = Booking::query()->create([
                'venue_id' => $data['venue_id'],
                'client_id' => $clientId,
                'lead_id' => $data['lead_id'] ?? null,
                'owner_user_id' => $owner->id,
                'name' => $data['name'],
                'kind' => $data['kind'],
                'status' => $data['status'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'total_cents' => $totalCents,
                'attendance_estimate' => $data['attendance_estimate'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['spaces'] as $spaceId) {
                BookingSpace::query()->create([
                    'booking_id' => $booking->id,
                    'space_id' => $spaceId,
                    'start_at' => $data['start_at'],
                    'end_at' => $data['end_at'],
                    'setup_minutes_before' => self::SPACE_BUFFER_MINUTES,
                    'teardown_minutes_after' => self::SPACE_BUFFER_MINUTES,
                ]);
            }

            // If this booking was converted from a lead, stamp the lead once.
            if (! empty($data['lead_id'])) {
                Lead::query()
                    ->where('id', $data['lead_id'])
                    ->whereNull('converted_booking_id')
                    ->update([
                        'converted_at' => now(),
                        'converted_booking_id' => $booking->id,
                    ]);
            }

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated BookingUpdateRequest data
     */
    public function update(Booking $booking, array $data): Booking
    {
        $totalCents = isset($data['total_dollars']) ? Money::toCents($data['total_dollars']) : 0;

        return DB::transaction(function () use ($booking, $data, $totalCents) {
            $booking->update([
                'venue_id' => $data['venue_id'],
                'client_id' => $data['client_id'],
                'name' => $data['name'],
                'kind' => $data['kind'],
                'status' => $data['status'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'total_cents' => $totalCents,
                'attendance_estimate' => $data['attendance_estimate'] ?? null,
                'notes' => $data['notes'] ?? null,
                'cancel_reason' => $data['cancel_reason'] ?? null,
            ]);

            // Sync spaces: drop ones no longer selected; add new ones.
            $wanted = array_map('intval', $data['spaces']);
            $current = $booking->spaces()->pluck('space_id')->all();

            $toRemove = array_diff($current, $wanted);
            if (! empty($toRemove)) {
                $booking->spaces()->whereIn('space_id', $toRemove)->delete();
            }

            $toAdd = array_diff($wanted, $current);
            foreach ($toAdd as $spaceId) {
                BookingSpace::query()->create([
                    'booking_id' => $booking->id,
                    'space_id' => $spaceId,
                    'start_at' => $data['start_at'],
                    'end_at' => $data['end_at'],
                    'setup_minutes_before' => self::SPACE_BUFFER_MINUTES,
                    'teardown_minutes_after' => self::SPACE_BUFFER_MINUTES,
                ]);
            }

            // Existing spaces that stayed: realign their time window to the
            // booking's new start/end so overlap checks reflect the move.
            $toUpdate = array_intersect($current, $wanted);
            if (! empty($toUpdate)) {
                foreach ($booking->spaces()->whereIn('space_id', $toUpdate)->get() as $bookingSpace) {
                    $bookingSpace->start_at = $data['start_at'];
                    $bookingSpace->end_at = $data['end_at'];
                    $bookingSpace->save();
                }
            }

            return $booking;
        });
    }
}
