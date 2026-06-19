<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\StaffAssignment;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff roster on a Booking: user, role, shift window, billable rate.
 */
class BookingStaffController extends Controller
{
    public function store(Booking $booking, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'max:80'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'hourly_rate_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = StaffAssignment::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'hourly_rate_cents' => $data['hourly_rate_cents'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $auditLogger->record(
            eventType: 'booking.staff_assigned',
            subject: $assignment,
            payload: [
                'booking_id' => $booking->id,
                'user_id' => $data['user_id'],
                'role' => $data['role'],
                'actor_user_id' => $request->user()?->id,
            ],
        );

        return back()->with('status', 'Staff assignment added.');
    }

    public function destroy(StaffAssignment $assignment, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $assignment->booking);

        $bookingId = $assignment->booking_id;
        $payload = [
            'booking_id' => $bookingId,
            'user_id' => $assignment->user_id,
            'role' => $assignment->role,
            'actor_user_id' => $request->user()?->id,
        ];

        $assignment->delete();

        $auditLogger->record(
            eventType: 'booking.staff_unassigned',
            subject: $assignment,
            payload: $payload,
        );

        return back()->with('status', 'Staff assignment removed.');
    }
}
