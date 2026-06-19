<?php

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists fiscal years', function () {
    FiscalYear::factory()->count(2)->create();

    $response = $this->actingAs($this->user)->get('/admin/fiscal-years');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/fiscal-years/index')
            ->has('years', 2)
        );
});

it('creates a fiscal year', function () {
    $response = $this->actingAs($this->user)->post('/admin/fiscal-years', [
        'label' => 'FY99',
        'starts_on' => '2099-10-01',
        'ends_on' => '2100-09-30',
    ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect(FiscalYear::where('label', 'FY99')->exists())->toBeTrue();
});

it('rejects a fiscal year whose end is before start', function () {
    $response = $this->actingAs($this->user)->post('/admin/fiscal-years', [
        'label' => 'FY98',
        'starts_on' => '2098-10-01',
        'ends_on' => '2098-09-30',
    ]);

    $response->assertSessionHasErrors(['ends_on']);
});

it('renders the budget editor', function () {
    $year = FiscalYear::factory()->current()->create();

    $response = $this->actingAs($this->user)->get("/admin/fiscal-years/{$year->label}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/fiscal-years/show')
            ->where('year.label', $year->label)
            ->has('summary')
            ->has('accounts')
            ->has('funds')
            ->has('totals_by_type')
        );
});

it('stores a budget line', function () {
    $year = FiscalYear::factory()->current()->create();
    $account = ChartOfAccount::query()->where('code', '4200')->firstOrFail();

    $response = $this->actingAs($this->user)
        ->post("/admin/fiscal-years/{$year->label}/budgets", [
            'chart_of_account_id' => $account->id,
            'amount_cents' => 100_000_00,
        ]);

    $response->assertRedirect();
    expect(Budget::query()->count())->toBe(1);
});

it('updates a budget line via upsert', function () {
    $year = FiscalYear::factory()->current()->create();
    $account = ChartOfAccount::query()->where('code', '4200')->firstOrFail();
    $this->actingAs($this->user)->post("/admin/fiscal-years/{$year->label}/budgets", [
        'chart_of_account_id' => $account->id,
        'amount_cents' => 100_000_00,
    ]);

    $this->actingAs($this->user)->post("/admin/fiscal-years/{$year->label}/budgets", [
        'chart_of_account_id' => $account->id,
        'amount_cents' => 125_000_00,
    ])->assertRedirect();

    expect(Budget::query()->count())->toBe(1);
    expect((int) Budget::query()->value('amount_cents'))->toBe(125_000_00);
});

it('deletes a budget line', function () {
    $year = FiscalYear::factory()->current()->create();
    $account = ChartOfAccount::query()->where('code', '4200')->firstOrFail();
    $this->actingAs($this->user)->post("/admin/fiscal-years/{$year->label}/budgets", [
        'chart_of_account_id' => $account->id,
        'amount_cents' => 100_000_00,
    ]);
    $budget = Budget::query()->firstOrFail();

    $this->actingAs($this->user)
        ->delete("/admin/fiscal-years/{$year->label}/budgets/{$budget->id}")
        ->assertRedirect();

    expect(Budget::query()->count())->toBe(0);
});

it('refuses to delete a budget line in a closed year', function () {
    $year = FiscalYear::factory()->current()->create();
    $account = ChartOfAccount::query()->where('code', '4200')->firstOrFail();
    $this->actingAs($this->user)->post("/admin/fiscal-years/{$year->label}/budgets", [
        'chart_of_account_id' => $account->id,
        'amount_cents' => 100_000_00,
    ]);
    $budget = Budget::query()->firstOrFail();
    $year->update(['is_closed' => true, 'closed_at' => now()]);

    $this->actingAs($this->user)
        ->delete("/admin/fiscal-years/{$year->label}/budgets/{$budget->id}")
        ->assertRedirect();

    expect(Budget::query()->count())->toBe(1);
});

it('closes a fiscal year', function () {
    $year = FiscalYear::factory()->current()->create();

    $response = $this->actingAs($this->user)
        ->post("/admin/fiscal-years/{$year->label}/close");

    $response->assertRedirect();
    expect($year->fresh()->is_closed)->toBeTrue();
});

it('reopens a closed fiscal year', function () {
    $year = FiscalYear::factory()->closed()->create();

    $response = $this->actingAs($this->user)
        ->post("/admin/fiscal-years/{$year->label}/reopen");

    $response->assertRedirect();
    expect($year->fresh()->is_closed)->toBeFalse();
});

it('requires authentication', function () {
    $this->get('/admin/fiscal-years')->assertRedirect('/login');
});
