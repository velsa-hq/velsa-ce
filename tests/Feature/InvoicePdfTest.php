<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    Pdf::fake();
});

it('renders the invoice PDF view with the expected payload', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $client = Client::factory()->create(['name' => 'Acme Inc']);
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'name' => 'Casey Client',
        'email' => 'casey@acme.test',
    ]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $response = $this->actingAs($admin)
        ->get("/admin/invoices/{$invoice->number}/pdf");

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) use ($invoice) {
        expect($pdf->viewName)->toBe('pdf.invoice')
            ->and($pdf->viewData['invoice']->id)->toBe($invoice->id)
            ->and($pdf->viewData['billToName'])->toBe('Acme Inc')
            ->and($pdf->viewData['billToContact'])->toBe('Casey Client')
            ->and($pdf->viewData['billToEmail'])->toBe('casey@acme.test');

        return true;
    });
});

it('requires authentication on the PDF endpoint', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $response = $this->get("/admin/invoices/{$invoice->number}/pdf");

    $response->assertRedirect(route('login'));
});
