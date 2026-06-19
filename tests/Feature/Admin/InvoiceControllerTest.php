<?php

use App\Enums\InvoiceStatus;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('renders the admin invoice index with summary rollups', function () {
    Invoice::factory()->count(3)->create();
    Invoice::factory()->pastDue()->create();

    $response = $this->actingAs($this->user)->get('/admin/invoices');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/invoices/index')
            ->has('invoices.data', 4)
            ->has('summary.total_outstanding_cents')
            ->has('summary.past_due_cents')
        );
});

it('filters invoices by status', function () {
    Invoice::factory()->paid()->create();
    Invoice::factory()->create(['status' => InvoiceStatus::Issued->value]);

    $response = $this->actingAs($this->user)
        ->get('/admin/invoices?status=paid');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->has('invoices.data', 1)
            ->where('invoices.data.0.status', 'paid')
        );
});

it('renders the invoice detail page', function () {
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($this->user)->get("/admin/invoices/{$invoice->number}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/invoices/show')
            ->where('invoice.number', $invoice->number)
        );
});

it('voids an open invoice via the controller', function () {
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($this->user)
        ->post("/admin/invoices/{$invoice->number}/void", [
            'reason' => 'Issued in error',
        ]);

    $response->assertRedirect();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('renders the exhibitor statement with rollups', function () {
    $exhibitor = Exhibitor::factory()->create();
    $order = ExhibitorOrder::factory()->create(['exhibitor_id' => $exhibitor->id]);
    Invoice::factory()->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $order->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/admin/exhibitors/{$exhibitor->id}/statement");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/invoices/statement')
            ->has('invoices', 1)
            ->has('totals.balance_cents')
        );
});

it('requires auth for every endpoint', function () {
    $invoice = Invoice::factory()->create();

    $this->get('/admin/invoices')->assertRedirect('/login');
    $this->get("/admin/invoices/{$invoice->number}")->assertRedirect('/login');
    $this->post("/admin/invoices/{$invoice->number}/void", ['reason' => 'x'])
        ->assertRedirect('/login');
});
