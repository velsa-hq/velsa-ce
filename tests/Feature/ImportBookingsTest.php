<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\ImportJob;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\Venue;
use App\Services\Import\ImportRegistry;
use App\Services\Import\ImportService;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bookingsCsv(): string
{
    return <<<'CSV'
    name,venue,client,status,start_at,end_at,space,attendance,total
    Acme Gala,Sentinel Convention Center,Acme Corp,definite,2027-06-01 09:00,2027-06-01 17:00,Grand Hall,500,15000.00
    Mystery Venue Event,Nonexistent Venue,Acme Corp,definite,2027-06-02 09:00,2027-06-02 17:00,,,
    No Client Event,Sentinel Convention Center,Ghost Client,definite,2027-06-03 09:00,2027-06-03 17:00,,,
    Bad Dates,Sentinel Convention Center,Acme Corp,definite,2027-06-04 17:00,2027-06-04 09:00,,,
    Weird Status,Sentinel Convention Center,Acme Corp,wizard,2027-06-05 09:00,2027-06-05 17:00,,,
    CSV;
}

function uploadBookingsCsv(): ImportJob
{
    $admin = grantSuperAdmin();

    test()->actingAs($admin)->post('/admin/imports', [
        'kind' => 'bookings',
        'file' => UploadedFile::fake()->createWithContent('bookings.csv', bookingsCsv()),
        'has_header' => true,
    ])->assertRedirect();

    return ImportJob::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
    // bookings is a read-only-required kind, so commit happens with read-only on
    app(SystemSettings::class)->set('operations.read_only', true);
    $this->venue = Venue::factory()->create(['name' => 'Sentinel Convention Center']);
    Client::factory()->create(['name' => 'Acme Corp']);
    Space::factory()->create(['name' => 'Grand Hall', 'venue_id' => $this->venue->id, 'capacity' => 800]);
});

it('auto-maps booking columns including aliased attendance', function () {
    $job = uploadBookingsCsv();

    expect($job->column_map['name'])->toBe('name')
        ->and($job->column_map['venue'])->toBe('venue')
        ->and($job->column_map['client'])->toBe('client')
        ->and($job->column_map['start_at'])->toBe('start_at')
        ->and($job->column_map['space'])->toBe('space')
        ->and($job->column_map['attendance_estimate'])->toBe('attendance')
        ->and($job->column_map['total'])->toBe('total');
});

it('previews, resolving FKs and flagging the bad rows', function () {
    $job = uploadBookingsCsv();

    $this->actingAs($job->creator)
        ->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map])
        ->assertRedirect();

    $job->refresh();

    // 1 valid, 4 errors (bad venue, missing client, bad dates, bad status)
    expect($job->total_rows)->toBe(5)
        ->and($job->valid_rows)->toBe(1)
        ->and($job->error_rows)->toBe(4)
        ->and(Booking::count())->toBe(0);

    $messages = $job->errors()->pluck('message')->implode(' | ');
    expect($messages)->toContain('Venue "Nonexistent Venue" not found')
        ->and($messages)->toContain('Client "Ghost Client" not found')
        ->and($messages)->toContain('End must be after start')
        ->and($messages)->toContain('Unrecognized status');
});

it('commits a booking with its space placement', function () {
    $job = uploadBookingsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit")->assertRedirect();

    $job->refresh();
    expect($job->created_rows)->toBe(1)
        ->and(Booking::count())->toBe(1)
        ->and(BookingSpace::count())->toBe(1);

    $booking = Booking::where('name', 'Acme Gala')->firstOrFail();
    expect($booking->status)->toBe(BookingStatus::Definite)
        ->and($booking->venue_id)->toBe($this->venue->id)
        ->and($booking->total_cents)->toBe(1_500_000)
        ->and($booking->attendance_estimate)->toBe(500)
        ->and($booking->spaces()->first()->space->name)->toBe('Grand Hall');
});

it('reverses a committed booking import', function () {
    $job = uploadBookingsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    expect(Booking::count())->toBe(1);

    app(ImportService::class)->reverse($job->refresh());

    expect(Booking::count())->toBe(0)
        ->and(BookingSpace::count())->toBe(0);
});

it('keeps an imported booking that has since been invoiced', function () {
    $job = uploadBookingsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    $booking = Booking::where('name', 'Acme Gala')->firstOrFail();
    Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $booking->id,
    ]);

    app(ImportService::class)->reverse($job->refresh());

    expect(Booking::where('name', 'Acme Gala')->exists())->toBeTrue()
        ->and($job->refresh()->summary_json['reversal']['skipped'])->toBe(1);
});

it('requires read-only mode for bulk booking import', function () {
    $importer = app(ImportRegistry::class)->get('bookings');

    expect($importer?->requiresReadOnly())->toBeTrue();
});
