<?php

use App\Enums\ImportStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ImportJob;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use App\Services\Import\ImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const CLIENTS_CSV = <<<'CSV'
name,type,industry,email,phone,contact
Acme Corp,business,Manufacturing,jane@acme.test,555-1000,Jane Doe
City of Testville,government,Public,,,
Bad Email Inc,business,Tech,not-an-email,,
,business,,,,
Weird Type Co,wizard,,,,
CSV;

/** Upload the sample CSV and return the created job. */
function uploadClientsCsv(): ImportJob
{
    $admin = grantSuperAdmin();

    test()->actingAs($admin)->post('/admin/imports', [
        'kind' => 'clients',
        'file' => UploadedFile::fake()->createWithContent('clients.csv', CLIENTS_CSV),
        'has_header' => true,
    ])->assertRedirect();

    return ImportJob::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
});

it('auto-maps obvious columns on upload', function () {
    $job = uploadClientsCsv();

    expect($job->status)->toBe(ImportStatus::Pending)
        ->and($job->column_map['name'])->toBe('name')
        ->and($job->column_map['type'])->toBe('type')
        ->and($job->column_map['primary_contact_email'])->toBe('email')
        ->and($job->column_map['primary_contact_phone'])->toBe('phone')
        ->and($job->column_map['primary_contact_name'])->toBe('contact');
});

it('previews without writing and reports per-row errors', function () {
    $job = uploadClientsCsv();
    $admin = $job->creator ?? grantSuperAdmin();

    $this->actingAs($admin)
        ->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map])
        ->assertRedirect();

    $job->refresh();

    expect($job->status)->toBe(ImportStatus::Previewed)
        ->and($job->total_rows)->toBe(5)
        ->and($job->valid_rows)->toBe(2)
        ->and($job->error_rows)->toBe(3)
        ->and(Client::count())->toBe(0); // dry run wrote nothing

    // bad email, missing name, bad type
    $messages = $job->errors()->pluck('message')->implode(' | ');
    expect($messages)->toContain('email')
        ->and($messages)->toContain('Unrecognized client type');
});

it('blocks preview until required fields are mapped', function () {
    $job = uploadClientsCsv();

    $this->actingAs($job->creator)
        ->post("/admin/imports/{$job->id}/preview", ['column_map' => ['name' => null]])
        ->assertSessionHasErrors('column_map');
});

it('commits valid rows, creating clients, contacts, and import records', function () {
    $job = uploadClientsCsv();

    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit")->assertRedirect();

    $job->refresh();

    expect($job->status)->toBe(ImportStatus::Completed)
        ->and($job->created_rows)->toBe(2)
        ->and($job->error_rows)->toBe(3)
        ->and(Client::count())->toBe(2)
        ->and(Contact::count())->toBe(1) // only Acme has a contact
        ->and($job->records()->count())->toBe(3); // 2 clients + 1 contact

    $acme = Client::where('name', 'Acme Corp')->firstOrFail();
    expect($acme->primaryContact?->email)->toBe('jane@acme.test')
        ->and($acme->type->value)->toBe('business');
});

it('reverses a committed import, deleting only what it created', function () {
    $job = uploadClientsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    expect(Client::count())->toBe(2);

    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/reverse")->assertRedirect();

    $job->refresh();
    expect($job->status)->toBe(ImportStatus::Reversed)
        ->and(Client::count())->toBe(0)
        ->and(Contact::count())->toBe(0);
});

it('refuses to remove an imported client now referenced by a lead', function () {
    $job = uploadClientsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    // a lead references the imported Acme client after the import
    $acme = Client::where('name', 'Acme Corp')->firstOrFail();
    Lead::factory()->create(['client_id' => $acme->id]);

    app(ImportService::class)->reverse($job->refresh());

    // Acme (referenced) stays with its contact; the other client is gone
    expect(Client::where('name', 'Acme Corp')->exists())->toBeTrue()
        ->and(Client::where('name', 'City of Testville')->exists())->toBeFalse()
        ->and($job->refresh()->summary_json['reversal']['skipped'])->toBe(1)
        ->and($job->summary_json['reversal']['deleted'])->toBe(1);
});

it('downloads the error report as CSV', function () {
    $job = uploadClientsCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);

    $response = $this->actingAs($job->creator)->get("/admin/imports/{$job->id}/errors");
    $response->assertOk();

    $body = $response->streamedContent();
    expect($body)->toContain('row_number,field,message')
        ->and($body)->toContain('Unrecognized client type');
});

it('gates the import area behind the data.import permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $roleUser = function (string $role) {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRoleAt(Venue::factory()->create(), $role);

        return $user;
    };

    // sales_rep lacks data.import
    $this->actingAs($roleUser('sales_rep'))->get('/admin/imports')->assertForbidden();

    // org_admin has data.import
    $this->actingAs($roleUser('org_admin'))->get('/admin/imports')->assertOk();
});
