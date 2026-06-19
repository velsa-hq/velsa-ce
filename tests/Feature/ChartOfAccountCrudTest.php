<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->admin = grantSuperAdmin();
});

it('creates an account', function () {
    $this->actingAs($this->admin)
        ->post('/admin/chart-of-accounts', [
            'code' => '4950',
            'name' => 'Parking Revenue',
            'account_type' => 'revenue',
            'is_postable' => true,
        ])
        ->assertRedirect();

    $account = ChartOfAccount::where('code', '4950')->first();
    expect($account)->not->toBeNull();
    expect($account->normal_balance)->toBe('credit');
});

it('rejects a duplicate code', function () {
    $this->actingAs($this->admin)
        ->post('/admin/chart-of-accounts', [
            'code' => '1010',
            'name' => 'Dupe',
            'account_type' => 'asset',
        ])
        ->assertSessionHasErrors('code');
});

it('updates an account name', function () {
    $this->actingAs($this->admin)
        ->put('/admin/chart-of-accounts/4900', [
            'code' => '4900',
            'name' => 'Other Revenue (renamed)',
            'account_type' => 'revenue',
            'is_postable' => true,
        ])
        ->assertRedirect();

    expect(ChartOfAccount::where('code', '4900')->value('name'))
        ->toBe('Other Revenue (renamed)');
});

it('locks code and type once the account has journal entries', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'x', 'debit_cents' => 100, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->admin)
        ->put('/admin/chart-of-accounts/1010', [
            'code' => '1011',
            'name' => 'Cash',
            'account_type' => 'asset',
            'is_postable' => true,
        ])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->admin)
        ->put('/admin/chart-of-accounts/1010', [
            'code' => '1010',
            'name' => 'Cash',
            'account_type' => 'liability',
            'is_postable' => true,
        ])
        ->assertSessionHasErrors('account_type');
});

it('refuses to delete an account that has journal entries', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'x', 'debit_cents' => 100, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->admin)
        ->delete('/admin/chart-of-accounts/1010')
        ->assertRedirect();

    expect(ChartOfAccount::where('code', '1010')->exists())->toBeTrue();
});

it('deletes an unused account', function () {
    ChartOfAccount::create([
        'code' => '4960',
        'name' => 'Temp',
        'account_type' => 'revenue',
        'is_postable' => true,
    ]);

    $this->actingAs($this->admin)
        ->delete('/admin/chart-of-accounts/4960')
        ->assertRedirect();

    expect(ChartOfAccount::where('code', '4960')->exists())->toBeFalse();
});

it('prevents a parent cycle', function () {
    $parent = ChartOfAccount::where('code', '4000')->firstOrFail();
    $child = ChartOfAccount::create([
        'code' => '4910',
        'name' => 'Child',
        'account_type' => 'revenue',
        'is_postable' => true,
        'parent_account_id' => $parent->id,
    ]);

    // make the roll-up a child of its own descendant
    $this->actingAs($this->admin)
        ->put("/admin/chart-of-accounts/{$parent->code}", [
            'code' => $parent->code,
            'name' => $parent->name,
            'account_type' => 'revenue',
            'is_postable' => false,
            'parent_account_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_account_id');
});

it('gates writes behind accounting.post_journal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/admin/chart-of-accounts', [
            'code' => '4970',
            'name' => 'Nope',
            'account_type' => 'revenue',
        ])
        ->assertForbidden();
});
