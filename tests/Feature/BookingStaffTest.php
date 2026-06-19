<?php

use App\Models\Booking;
use App\Models\Department;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // issuing an invoice posts the revenue accrual, which needs the chart of accounts
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin();
});

it('assigns a staff member to a booking with role + shift + rate', function () {
    $booking = Booking::factory()->create();
    $staffMember = User::factory()->create(['name' => 'Maya Chen']);

    $this->actingAs($this->user)
        ->post("/bookings/{$booking->id}/staff", [
            'user_id' => $staffMember->id,
            'role' => 'Event lead',
            'start_at' => '2026-08-01 12:00',
            'end_at' => '2026-08-01 22:00',
            'hourly_rate_cents' => 5500,
            'notes' => 'Lead for the gala',
        ])
        ->assertRedirect();

    $assignment = StaffAssignment::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($assignment->user_id)->toBe($staffMember->id)
        ->and($assignment->role)->toBe('Event lead')
        ->and($assignment->hourly_rate_cents)->toBe(5500);
});

it('rejects an assignment where end_at is not after start_at', function () {
    $booking = Booking::factory()->create();
    $staffMember = User::factory()->create();

    $this->actingAs($this->user)
        ->post("/bookings/{$booking->id}/staff", [
            'user_id' => $staffMember->id,
            'role' => 'AV',
            'start_at' => '2026-08-01 14:00',
            'end_at' => '2026-08-01 14:00',
            'hourly_rate_cents' => 3500,
        ])
        ->assertSessionHasErrors('end_at');
});

it('removes a staff assignment via DELETE', function () {
    $booking = Booking::factory()->create();
    $assignment = StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->user)
        ->delete("/staff-assignments/{$assignment->id}")
        ->assertRedirect();

    expect(StaffAssignment::query()->find($assignment->id))->toBeNull();
});

it('exposes staff on the booking show Inertia payload', function () {
    $booking = Booking::factory()->create();
    $staffMember = User::factory()->create(['name' => 'Carlos Mendez']);
    StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $staffMember->id,
        'role' => 'Ops lead',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(8),
        'hourly_rate_cents' => 5000,
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/show')
            ->has('staff', 1)
            ->where('staff.0.role', 'Ops lead')
            ->where('staff.0.user.name', 'Carlos Mendez')
            ->has('staff_candidates'));
});

it('computes labor cost as hours x rate', function () {
    $booking = Booking::factory()->create();
    $assignment = StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => grantSuperAdmin()->id,
        'start_at' => '2026-08-01 12:00',
        'end_at' => '2026-08-01 20:00',
        'hourly_rate_cents' => 5000,
    ]);

    expect($assignment->durationHours())->toBe(8.0)
        ->and($assignment->laborCostCents())->toBe(40000);
});

it('updates an outline item including the responsible user', function () {
    Department::factory()->system()->create(['key' => 'setup', 'label' => 'Setup']);
    Department::factory()->system()->create(['key' => 'av', 'label' => 'A/V']);
    $booking = Booking::factory()->create();
    $outline = EventOutline::query()->create([
        'booking_id' => $booking->id,
        'published_version' => 0,
    ]);
    $item = OutlineItem::query()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => '2026-08-01 12:00',
        'duration_minutes' => 30,
        'department' => 'setup',
        'title' => 'Original title',
    ]);
    $responsible = grantSuperAdmin();

    $this->actingAs($this->user)
        ->patch("/outline-items/{$item->id}", [
            'scheduled_at' => '2026-08-01 13:00',
            'duration_minutes' => 45,
            'department' => 'av',
            'title' => 'Updated title',
            'description' => 'Why it changed',
            'responsible_user_id' => $responsible->id,
        ])
        ->assertRedirect();

    $fresh = $item->fresh();
    expect($fresh->title)->toBe('Updated title')
        ->and($fresh->duration_minutes)->toBe(45)
        ->and($fresh->department)->toBe('av')
        ->and($fresh->responsible_user_id)->toBe($responsible->id);
});

it('exposes the booking staff roster as responsible candidates', function () {
    $booking = Booking::factory()->create();
    $userA = User::factory()->create(['name' => 'Person A']);
    $userB = User::factory()->create(['name' => 'Person B']);
    StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $userA->id,
        'role' => 'Lead',
    ]);
    StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $userB->id,
        'role' => 'AV',
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}/outline")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/outline')
            ->has('staff', 2)
            ->where(
                'staff',
                fn ($staff) => collect($staff)->pluck('name')->sort()->values()->all() === ['Person A', 'Person B'],
            ));
});

it('deduplicates staff candidates when one user has multiple shifts', function () {
    $booking = Booking::factory()->create();
    $user = grantSuperAdmin();
    StaffAssignment::factory()->count(2)->create([
        'booking_id' => $booking->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}/outline")
        ->assertInertia(fn ($page) => $page->has('staff', 1));
});

it('preserves outline item responsible_user_id when the staff assignment is deleted', function () {
    // deleting a person keeps their attribution on any outline items they own
    $booking = Booking::factory()->create();
    $responsible = User::factory()->create(['name' => 'Maya Chen']);

    $assignment = StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => $responsible->id,
    ]);

    $outline = EventOutline::query()->create([
        'booking_id' => $booking->id,
        'published_version' => 0,
    ]);
    $item = OutlineItem::query()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => '2026-08-01 12:00',
        'duration_minutes' => 30,
        'department' => 'setup',
        'title' => 'Setup',
        'responsible_user_id' => $responsible->id,
    ]);

    $this->actingAs($this->user)
        ->delete("/staff-assignments/{$assignment->id}")
        ->assertRedirect();

    expect(StaffAssignment::query()->find($assignment->id))->toBeNull()
        ->and($item->fresh()->responsible_user_id)->toBe($responsible->id);
});

it('does not roll up staff labor cost into the booking total', function () {
    // labor cost is a sales/ops number, never flows into the booking total
    $booking = Booking::factory()->create([
        'total_cents' => 250_000_00,
    ]);
    $beforeTotal = $booking->total_cents;

    StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => grantSuperAdmin()->id,
        'start_at' => '2026-08-01 12:00',
        'end_at' => '2026-08-01 22:00',
        'hourly_rate_cents' => 5500,
    ]);

    expect($booking->fresh()->total_cents)->toBe($beforeTotal);
});

it('does not include staff labor cost in any auto-issued invoice', function () {
    // deposit issuance looks only at the booking's total_cents
    $booking = Booking::factory()->create([
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);

    StaffAssignment::factory()->create([
        'booking_id' => $booking->id,
        'user_id' => grantSuperAdmin()->id,
        'start_at' => '2026-08-01 12:00',
        'end_at' => '2026-08-01 22:00',
        'hourly_rate_cents' => 10_00,
    ]);

    $invoice = app(InvoiceService::class)
        ->issueDepositForBooking($booking);

    // deposit = 50% of $100,000, labor cost not added
    expect($invoice->total_cents)->toBe(50_000_00);
});

it('does not cascade outline item timestamps when the booking start_at changes', function () {
    // outline items use absolute timestamps, not offsets; moving the booking does not shift them
    $booking = Booking::factory()->create([
        'start_at' => '2026-08-01 09:00',
        'end_at' => '2026-08-01 23:00',
    ]);
    $outline = EventOutline::query()->create([
        'booking_id' => $booking->id,
        'published_version' => 0,
    ]);
    $item = OutlineItem::query()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => '2026-08-01 12:00',
        'duration_minutes' => 60,
        'department' => 'catering',
        'title' => 'Lunch service',
    ]);

    $booking->update([
        'start_at' => '2026-09-15 09:00',
        'end_at' => '2026-09-15 23:00',
    ]);

    expect($item->fresh()->scheduled_at?->toDateTimeString())
        ->toBe('2026-08-01 12:00:00');
});
