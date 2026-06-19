<?php

use App\Enums\ExhibitorOrderStatus;
use App\Mail\ExhibitorPortalLink;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
        'email' => 'vendor@example.test',
    ]);
    $this->order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($this->order, $chair, 10);
    $this->order->recalculateTotals();
});

it('renders the portal payment page with the order balance', function () {
    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->get("/portal/orders/{$this->order->id}/pay");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('portal/order-pay')
            ->where('order.id', $this->order->id)
            ->where('order.balance_cents', $this->order->fresh()->balanceCents())
        );
});

it('redirects away from the payment page when the order has no balance', function () {
    $this->order->update(['paid_cents' => $this->order->total_cents, 'status' => 'paid']);

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->get("/portal/orders/{$this->order->id}/pay");

    $response->assertRedirect("/portal/orders/{$this->order->id}");
});

it('captures a payment, marks the order paid, and posts journal entries', function () {
    $balance = $this->order->fresh()->balanceCents();

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/orders/{$this->order->id}/pay", [
            'card_token' => 'visa_tok_42424242',
            'amount_cents' => $balance,
        ]);

    $response->assertRedirect("/portal/orders/{$this->order->id}");

    $fresh = $this->order->fresh();
    expect($fresh->paid_cents)->toBe($balance)
        ->and($fresh->status?->value)->toBe(ExhibitorOrderStatus::Paid->value)
        ->and($fresh->payments)->toHaveCount(1)
        ->and($fresh->payments->first()->card_brand)->toBe('visa');
});

it('accepts a partial payment and keeps the order at partially_paid', function () {
    $balance = $this->order->fresh()->balanceCents();
    $partial = (int) floor($balance / 3);

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/orders/{$this->order->id}/pay", [
            'card_token' => 'mc_tok_5555',
            'amount_cents' => $partial,
        ]);

    $response->assertRedirect();
    $fresh = $this->order->fresh();
    expect($fresh->paid_cents)->toBe($partial)
        ->and($fresh->status?->value)->toBe(ExhibitorOrderStatus::PartiallyPaid->value);
});

it('records a declined payment and keeps the balance unchanged', function () {
    $beforePaid = $this->order->fresh()->paid_cents;

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/orders/{$this->order->id}/pay", [
            'card_token' => 'visa_tok_0000',
            'amount_cents' => $this->order->fresh()->balanceCents(),
        ]);

    $response->assertRedirect();
    $fresh = $this->order->fresh();
    expect($fresh->paid_cents)->toBe($beforePaid)
        ->and($fresh->payments)->toHaveCount(1)
        ->and($fresh->payments->first()->status?->value)->toBe('failed');
});

it('clamps the amount to the order balance', function () {
    $balance = $this->order->fresh()->balanceCents();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/orders/{$this->order->id}/pay", [
            'card_token' => 'visa_tok_42424242',
            'amount_cents' => $balance + 50_000, // overshoot
        ]);

    $fresh = $this->order->fresh();
    expect($fresh->paid_cents)->toBe($balance);
});

it('blocks paying another exhibitor\'s order', function () {
    $other = Exhibitor::factory()->create([
        'exhibitor_event_id' => $this->exhibitor->exhibitor_event_id,
    ]);

    $response = $this->actingAs($other, 'exhibitor')
        ->post("/portal/orders/{$this->order->id}/pay", [
            'card_token' => 'visa_tok_4242',
            'amount_cents' => 1000,
        ]);

    $response->assertNotFound();
});

it('renders the print-optimized invoice page', function () {
    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->get("/portal/orders/{$this->order->id}/invoice");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('portal/invoice')
            ->where('order.id', $this->order->id)
            ->has('order.items')
            ->has('exhibitor')
            ->has('event')
        );
});

it('blocks viewing another exhibitor\'s invoice', function () {
    $other = Exhibitor::factory()->create([
        'exhibitor_event_id' => $this->exhibitor->exhibitor_event_id,
    ]);

    $response = $this->actingAs($other, 'exhibitor')
        ->get("/portal/orders/{$this->order->id}/invoice");

    $response->assertNotFound();
});

it('emails the portal link mailable when admin issues one to an exhibitor with email', function () {
    Mail::fake();
    $admin = grantSuperAdmin();

    $this->actingAs($admin)
        ->post("/exhibitors/{$this->exhibitor->id}/portal-link");

    Mail::assertSent(ExhibitorPortalLink::class, function (ExhibitorPortalLink $mail) {
        return $mail->hasTo($this->exhibitor->email)
            && str_contains($mail->loginUrl, '/portal/login/');
    });
});

it('does not attempt to send mail when the exhibitor has no email', function () {
    Mail::fake();
    $this->exhibitor->update(['email' => '']);
    $admin = grantSuperAdmin();

    $this->actingAs($admin)
        ->post("/exhibitors/{$this->exhibitor->id}/portal-link");

    Mail::assertNothingSent();
});

it('still issues a token when the exhibitor has no email', function () {
    Mail::fake();
    $this->exhibitor->update(['email' => '']);
    $admin = grantSuperAdmin();

    $this->actingAs($admin)
        ->post("/exhibitors/{$this->exhibitor->id}/portal-link");

    expect($this->exhibitor->fresh()->magic_token)->not->toBeNull();
});
