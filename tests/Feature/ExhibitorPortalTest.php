<?php

use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Services\MagicLinkService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
    ]);
    $this->service = app(MagicLinkService::class);
});

it('logs an exhibitor in via a valid magic-link token', function () {
    $token = $this->service->issue($this->exhibitor);

    $response = $this->get("/portal/login/{$token}");

    $response->assertRedirect('/portal');
    expect(Auth::guard('exhibitor')->id())->toBe($this->exhibitor->id);
});

it('rejects an invalid magic-link token', function () {
    $response = $this->get('/portal/login/garbage-token');

    $response->assertRedirect('/');
    expect(Auth::guard('exhibitor')->check())->toBeFalse();
});

it('rejects an expired magic-link token', function () {
    $token = $this->service->issue($this->exhibitor);
    $this->exhibitor->forceFill(['magic_token_expires_at' => now()->subHour()])->save();

    $response = $this->get("/portal/login/{$token}");

    $response->assertRedirect('/');
    expect(Auth::guard('exhibitor')->check())->toBeFalse();
});

it('consumes the token after a successful login', function () {
    $token = $this->service->issue($this->exhibitor);

    $this->get("/portal/login/{$token}");
    Auth::guard('exhibitor')->logout();

    $response = $this->get("/portal/login/{$token}");

    $response->assertRedirect('/');
    expect(Auth::guard('exhibitor')->check())->toBeFalse();
});

it('blocks portal access without an exhibitor session', function () {
    $this->get('/portal')->assertRedirect('/login');
    $this->get('/portal/catalog')->assertRedirect('/login');
});

it('renders the portal dashboard for an authenticated exhibitor', function () {
    $response = $this->actingAs($this->exhibitor, 'exhibitor')->get('/portal');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('portal/dashboard')
            ->where('exhibitor.id', $this->exhibitor->id)
            ->has('event')
            ->has('order_history')
            ->has('totals')
        );
});

it('renders the portal catalog grouped by category', function () {
    $response = $this->actingAs($this->exhibitor, 'exhibitor')->get('/portal/catalog');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('portal/catalog')
            ->has('categories')
        );
});

it('lazily creates a draft order on first add-to-cart', function () {
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->post('/portal/orders/items', [
            'equipment_item_id' => $item->id,
            'quantity' => 4,
        ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    $order = $this->exhibitor->orders()->first();

    expect($order)->not->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->sku)->toBe('CHAIR-FOLD')
        ->and($order->items->first()->quantity)->toBe(4)
        ->and($order->subtotal_cents)->toBe(4 * 800);
});

it('reuses the current draft order on subsequent adds', function () {
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    $table = EquipmentItem::query()->where('sku', 'TABLE-6FT')->firstOrFail();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post('/portal/orders/items', ['equipment_item_id' => $chair->id, 'quantity' => 2]);
    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post('/portal/orders/items', ['equipment_item_id' => $table->id, 'quantity' => 1]);

    expect($this->exhibitor->orders()->count())->toBe(1)
        ->and($this->exhibitor->orders()->first()->items)->toHaveCount(2);
});

it('blocks a portal user from viewing another exhibitor\'s order', function () {
    $otherExhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $this->exhibitor->exhibitor_event_id,
    ]);
    $otherOrder = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $otherExhibitor->id,
    ]);

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->get("/portal/orders/{$otherOrder->id}");

    $response->assertNotFound();
});

it('blocks an exhibitor from removing an item on someone else\'s order', function () {
    $other = Exhibitor::factory()->create([
        'exhibitor_event_id' => $this->exhibitor->exhibitor_event_id,
    ]);
    $otherOrder = ExhibitorOrder::factory()->create(['exhibitor_id' => $other->id]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    $line = ExhibitorOrderItem::fromCatalog($otherOrder, $chair, 1);

    $response = $this->actingAs($this->exhibitor, 'exhibitor')
        ->delete("/portal/orders/{$otherOrder->id}/items/{$line->id}");

    $response->assertNotFound();
});

it('logs the exhibitor out and clears the session', function () {
    $this->actingAs($this->exhibitor, 'exhibitor');
    expect(Auth::guard('exhibitor')->check())->toBeTrue();

    $response = $this->post('/portal/logout');

    $response->assertRedirect('/');
    expect(Auth::guard('exhibitor')->check())->toBeFalse();
});

it('issues a portal link via the admin endpoint and flashes the URL', function () {
    $admin = grantSuperAdmin();

    $response = $this->actingAs($admin)
        ->post("/exhibitors/{$this->exhibitor->id}/portal-link");

    $response->assertRedirect();
    expect($this->exhibitor->fresh()->magic_token)->not->toBeNull();
});
