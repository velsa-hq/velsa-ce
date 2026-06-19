<?php

use App\Enums\ImportStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Fund;
use App\Models\ImportJob;
use App\Models\Space;
use App\Models\Venue;
use App\Services\SystemSettings\SystemSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function readOnly(bool $on): void
{
    app(SystemSettings::class)->set('operations.read_only', $on);
}

it('blocks a mutating request while read-only', function () {
    $admin = grantSuperAdmin();
    readOnly(true);

    $this->actingAs($admin)
        ->post('/admin/funds', ['code' => 'NEWF', 'name' => 'New Fund', 'fund_type' => 'general'])
        ->assertRedirect();

    expect(Fund::where('code', 'NEWF')->exists())->toBeFalse();
});

it('still allows reads while read-only', function () {
    $admin = grantSuperAdmin();
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    readOnly(true);

    $this->actingAs($admin)->get('/admin/funds')->assertOk();
});

it('lets an admin turn read-only mode back off', function () {
    $admin = grantSuperAdmin();
    readOnly(true);

    $this->actingAs($admin)
        ->put('/admin/system-settings', ['values' => ['operations.read_only' => false]])
        ->assertRedirect();

    expect((bool) app(SystemSettings::class)->get('operations.read_only'))->toBeFalse();
});

it('allows logout while read-only', function () {
    $admin = grantSuperAdmin();
    readOnly(true);

    $this->actingAs($admin)->post('/logout')->assertRedirect();
});

it('allows the data importer while read-only', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    readOnly(true);

    $this->actingAs($admin)->post('/admin/imports', [
        'kind' => 'clients',
        'file' => UploadedFile::fake()->createWithContent('c.csv', "name\nAcme Corp"),
        'has_header' => true,
    ])->assertRedirect();

    expect(ImportJob::count())->toBe(1);
});

it('shares the read-only flag to the front end', function () {
    $admin = grantSuperAdmin();
    readOnly(true);

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn (Assert $page) => $page->where('readOnly', true),
    );
});

it('requires read-only mode to commit a bookings import', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create(['name' => 'Hall A Venue']);
    Client::factory()->create(['name' => 'Acme Corp']);
    Space::factory()->create(['name' => 'Hall A', 'venue_id' => $venue->id]);

    $csv = "name,venue,client,start_at,end_at,space\n".
        'Gala,Hall A Venue,Acme Corp,2027-07-01 09:00,2027-07-01 17:00,Hall A';

    $this->actingAs($admin)->post('/admin/imports', [
        'kind' => 'bookings',
        'file' => UploadedFile::fake()->createWithContent('b.csv', $csv),
        'has_header' => true,
    ]);
    $job = ImportJob::latest('id')->firstOrFail();
    $this->actingAs($admin)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);

    // not read-only -> commit refused, nothing created
    $this->actingAs($admin)->post("/admin/imports/{$job->id}/commit")->assertRedirect();
    expect(Booking::count())->toBe(0)
        ->and($job->refresh()->status)->toBe(ImportStatus::Previewed);

    // read-only on -> commit proceeds and is marked covered
    readOnly(true);
    $this->actingAs($admin)->post("/admin/imports/{$job->id}/commit")->assertRedirect();
    expect(Booking::count())->toBe(1)
        ->and($job->refresh()->status)->toBe(ImportStatus::Completed)
        ->and($job->read_only_covered)->toBeTrue();
});
