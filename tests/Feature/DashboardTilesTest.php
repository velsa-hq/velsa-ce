<?php

use App\Dashboard\DashboardTile;
use App\Dashboard\DashboardTileRegistry;
use App\Enums\BookingStatus;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStage;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * User holding the given role at one venue; super_admin sees every tile.
 */
function dashboardUserWith(string $role, ?array $preferences = null): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $venue = Venue::factory()->create();
    // factory create bypasses mass-assignment guarding, so prefs stick
    $user = User::factory()->create(['dashboard_preferences' => $preferences]);
    $user->assignRoleAt($venue, $role);

    return $user;
}

it('exposes all registered tiles in the catalog', function () {
    $user = dashboardUserWith('super_admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('catalog', 11)
        ->has('tiles')
        ->has('selected_keys'));
});

it('falls back to the default tile set when the user has no preferences', function () {
    $user = dashboardUserWith('super_admin');
    $registry = app(DashboardTileRegistry::class);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('selected_keys', $registry->defaultTileKeys()));
});

it('honors stored tile selections', function () {
    $user = dashboardUserWith('super_admin', [
        'tiles' => ['my_open_leads', 'past_due_invoices'],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('selected_keys', ['my_open_leads', 'past_due_invoices'])
        ->has('tiles', 2)
        ->where('tiles.0.key', 'my_open_leads')
        ->where('tiles.1.key', 'past_due_invoices'));
});

it('silently drops unknown tile keys from stored preferences', function () {
    $user = dashboardUserWith('super_admin', [
        'tiles' => ['my_open_leads', 'not_a_real_tile', 'past_due_invoices'],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('selected_keys', ['my_open_leads', 'past_due_invoices']));
});

it('gates the catalog by the user permissions (finance role)', function () {
    $user = dashboardUserWith('finance');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(function ($page) {
        $keys = collect($page->toArray()['props']['catalog'])->pluck('key')->all();

        // finance has accounting/bookings/reports/audit view, not pipeline or leads
        expect($keys)->toContain('past_due_invoices', 'needs_attention', 'revenue_trend', 'recent_activity')
            ->and($keys)->not->toContain('pipeline_by_stage', 'my_open_leads');
    });
});

it('shows a role-less user only the ungated tiles', function () {
    $user = User::factory()->create(['dashboard_preferences' => null]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(function ($page) {
        $keys = collect($page->toArray()['props']['catalog'])->pluck('key')->all();

        expect($keys)->toEqualCanonicalizing(['quick_links', 'kpi_strip']);
    });
});

it('saves the submitted tile order to the user preferences', function () {
    $user = User::factory()->create(['dashboard_preferences' => null]);

    $response = $this->actingAs($user)->put('/dashboard/preferences', [
        'tiles' => ['past_due_invoices', 'kpi_strip', 'my_open_leads'],
    ]);

    $response->assertRedirect(route('dashboard'));

    expect($user->fresh()->dashboard_preferences)->toBe([
        'tiles' => ['past_due_invoices', 'kpi_strip', 'my_open_leads'],
    ]);
});

it('drops unknown tile keys and dedupes when saving preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/dashboard/preferences', [
        'tiles' => ['kpi_strip', 'not_a_tile', 'kpi_strip', 'my_open_leads'],
    ]);

    expect($user->fresh()->dashboard_preferences)->toBe([
        'tiles' => ['kpi_strip', 'my_open_leads'],
    ]);
});

it('accepts an empty tile list', function () {
    $user = User::factory()->create([
        'dashboard_preferences' => ['tiles' => ['kpi_strip']],
    ]);

    $this->actingAs($user)->put('/dashboard/preferences', [
        'tiles' => [],
    ]);

    expect($user->fresh()->dashboard_preferences)->toBe(['tiles' => []]);
});

it('requires the tiles field to be present', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/dashboard/preferences', []);

    $response->assertSessionHasErrors('tiles');
});

it('renders the my-open-leads tile data scoped to the user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $client = Client::factory()->create();

    Lead::factory()->atStage(LeadStage::Qualified)->create([
        'owner_user_id' => $user->id,
        'client_id' => $client->id,
        'name' => 'Mine',
        'estimated_value_cents' => 100_000_00,
    ]);
    Lead::factory()->atStage(LeadStage::Qualified)->create([
        'owner_user_id' => $other->id,
        'client_id' => $client->id,
        'name' => 'Not mine',
    ]);

    $tile = app(DashboardTileRegistry::class)->get('my_open_leads');
    expect($tile)->not->toBeNull();

    $data = $tile->render($user);

    expect($data['leads'])->toHaveCount(1)
        ->and($data['leads'][0]['name'])->toBe('Mine');
});

it('renders the past-due-invoices tile only for past-due rows', function () {
    Invoice::factory()->create(['status' => InvoiceStatus::Issued->value]);
    Invoice::factory()->create(['status' => InvoiceStatus::PastDue->value]);
    Invoice::factory()->create(['status' => InvoiceStatus::PastDue->value]);

    $tile = app(DashboardTileRegistry::class)->get('past_due_invoices');
    $data = $tile->render(User::factory()->create());

    expect($data['count'])->toBe(2);
});

it('renders the my-upcoming-bookings tile filtered by owner and window', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'owner_user_id' => $user->id,
        'name' => 'Mine soon',
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(3)->addHours(8),
    ]);
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'owner_user_id' => $other->id,
        'name' => 'Not mine',
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(3)->addHours(8),
    ]);
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'owner_user_id' => $user->id,
        'name' => 'Too far out',
        'start_at' => now()->addDays(30),
        'end_at' => now()->addDays(30)->addHours(8),
    ]);

    $tile = app(DashboardTileRegistry::class)->get('my_upcoming_bookings');
    $data = $tile->render($user);

    expect($data['bookings'])->toHaveCount(1)
        ->and($data['bookings'][0]['name'])->toBe('Mine soon');
});

it('NeedsAttentionTile surfaces stale bookings, unopened contracts, and stuck leads', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();

    // stale tentative, touched 20d ago: counts
    $cold = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'name' => 'Cold tentative',
        'start_at' => now()->addDays(20),
        'end_at' => now()->addDays(20)->addHours(4),
    ]);
    $cold->narratives()->create([
        'author_user_id' => $user->id,
        'body' => 'Initial hold placed',
        'happened_at' => now()->subDays(20),
    ]);
    // brand-new, no activity: excluded
    Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'name' => 'Just created',
        'start_at' => now()->addDays(20),
        'end_at' => now()->addDays(20)->addHours(4),
    ]);
    // recently touched: excluded by the narrative check
    $fresh = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'name' => 'Fresh tentative',
        'start_at' => now()->addDays(20),
        'end_at' => now()->addDays(20)->addHours(4),
    ]);
    $fresh->narratives()->create([
        'author_user_id' => $user->id,
        'body' => 'Just spoke with the client',
        'happened_at' => now()->subDay(),
    ]);
    // definite: excluded by status
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'name' => 'Confirmed',
        'start_at' => now()->addDays(20),
        'end_at' => now()->addDays(20)->addHours(4),
    ]);

    // old unopened sent counts; recently sent and already viewed excluded
    Contract::factory()->create(['reference' => 'CT-OLD', 'status' => ContractStatus::Sent->value, 'sent_at' => now()->subDays(10)]);
    Contract::factory()->create(['reference' => 'CT-NEW', 'status' => ContractStatus::Sent->value, 'sent_at' => now()->subDays(2)]);
    Contract::factory()->create(['reference' => 'CT-SEEN', 'status' => ContractStatus::Viewed->value, 'sent_at' => now()->subDays(10), 'viewed_at' => now()->subDays(9)]);

    // stuck in ContractSent 20d counts; recent one excluded
    $stuck = Lead::factory()->atStage(LeadStage::ContractSent)->create(['client_id' => $client->id, 'name' => 'Stuck deal']);
    Lead::where('id', $stuck->id)->update(['updated_at' => now()->subDays(20)]);
    Lead::factory()->atStage(LeadStage::ContractSent)->create(['client_id' => $client->id, 'name' => 'Just sent']);

    $tile = app(DashboardTileRegistry::class)->get('needs_attention');
    expect($tile)->not->toBeNull();

    $byKey = collect($tile->render($user)['groups'])->keyBy('key');
    expect($byKey['stale_tentative_bookings']['count'])->toBe(1)
        ->and($byKey['unviewed_sent_contracts']['count'])->toBe(1)
        ->and($byKey['stuck_leads']['count'])->toBe(1);
});

it('exposes the quick-link catalog grouped by sidebar section', function () {
    $tile = app(DashboardTileRegistry::class)->get('quick_links');
    $data = $tile->render(User::factory()->create());

    $groupLabels = collect($data['available_groups'])->pluck('label')->all();
    expect($groupLabels)->toBe(['Sales', 'Operations', 'Finance', 'Reporting', 'Admin']);

    $financeItems = collect($data['available_groups'])
        ->firstWhere('label', 'Finance')['items'];
    expect(collect($financeItems)->pluck('key')->all())
        ->toContain('accounting', 'funds', 'invoices');
});

it('returns no selected quick-link chiclets for a fresh user', function () {
    $tile = app(DashboardTileRegistry::class)->get('quick_links');
    $data = $tile->render(User::factory()->create(['dashboard_preferences' => null]));

    expect($data['selected_keys'])->toBe([]);
});

it('returns the user-stored quick-link chiclets in order', function () {
    $user = User::factory()->create([
        'dashboard_preferences' => [
            'quick_link_keys' => ['accounting', 'funds', 'contracts'],
        ],
    ]);

    $tile = app(DashboardTileRegistry::class)->get('quick_links');
    $data = $tile->render($user);

    expect($data['selected_keys'])->toBe(['accounting', 'funds', 'contracts']);
});

it('lists quick_links first in the default tile set', function () {
    $registry = app(DashboardTileRegistry::class);

    expect($registry->defaultTileKeys()[0])->toBe('quick_links');
});

it('excludes recent_activity from the default tile set but keeps it in the catalog', function () {
    $registry = app(DashboardTileRegistry::class);

    expect($registry->defaultTileKeys())->not->toContain('recent_activity')
        ->and($registry->get('recent_activity'))->not->toBeNull();
});

it('saves quick-link selections to the user preferences', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/dashboard/quick-links', [
        'keys' => ['accounting', 'contracts', 'funds'],
    ]);

    $response->assertRedirect(route('dashboard'));

    expect($user->fresh()->dashboard_preferences)->toBe([
        'quick_link_keys' => ['accounting', 'contracts', 'funds'],
    ]);
});

it('drops unknown quick-link keys and dedupes when saving', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/dashboard/quick-links', [
        'keys' => ['accounting', 'not_a_real_link', 'accounting', 'funds'],
    ]);

    expect($user->fresh()->dashboard_preferences)->toBe([
        'quick_link_keys' => ['accounting', 'funds'],
    ]);
});

it('preserves the tile list when updating quick links and vice versa', function () {
    $user = User::factory()->create([
        'dashboard_preferences' => [
            'tiles' => ['kpi_strip', 'my_open_leads'],
            'quick_link_keys' => ['accounting'],
        ],
    ]);

    $this->actingAs($user)->put('/dashboard/quick-links', [
        'keys' => ['contracts', 'funds'],
    ]);

    expect($user->fresh()->dashboard_preferences)->toBe([
        'tiles' => ['kpi_strip', 'my_open_leads'],
        'quick_link_keys' => ['contracts', 'funds'],
    ]);
});

it('registers tiles by key', function () {
    $registry = new DashboardTileRegistry;

    $tile = new class extends DashboardTile
    {
        public function key(): string
        {
            return 'fake';
        }

        public function label(): string
        {
            return 'Fake';
        }

        public function description(): string
        {
            return 'A fake tile.';
        }

        public function render(User $user): array
        {
            return [];
        }
    };

    $registry->register($tile);

    expect($registry->get('fake'))->toBe($tile)
        ->and($registry->get('missing'))->toBeNull()
        ->and($registry->all())->toHaveKey('fake');
});
