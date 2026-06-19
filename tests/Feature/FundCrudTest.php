<?php

use App\Models\Fund;
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

it('creates a fund', function () {
    $this->actingAs($this->admin)
        ->post('/admin/funds', [
            'code' => 'GRANTS',
            'name' => 'Grants Fund',
            'fund_type' => 'special_revenue',
        ])
        ->assertRedirect();

    expect(Fund::where('code', 'GRANTS')->exists())->toBeTrue();
});

it('rejects a duplicate code', function () {
    $this->actingAs($this->admin)
        ->post('/admin/funds', [
            'code' => 'GENERAL',
            'name' => 'Dupe',
            'fund_type' => 'general',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a fund name', function () {
    $this->actingAs($this->admin)
        ->put('/admin/funds/TOURISM', [
            'code' => 'TOURISM',
            'name' => 'Tourism Fund (renamed)',
            'fund_type' => 'special_revenue',
        ])
        ->assertRedirect();

    expect(Fund::where('code', 'TOURISM')->value('name'))->toBe('Tourism Fund (renamed)');
});

it('locks the code once the fund has journal entries', function () {
    JournalEntry::post(['account_code' => '1010', 'fund_code' => 'GENERAL', 'description' => 'x', 'debit_cents' => 100, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->admin)
        ->put('/admin/funds/GENERAL', [
            'code' => 'GEN2',
            'name' => 'General Fund',
            'fund_type' => 'general',
        ])
        ->assertSessionHasErrors('code');
});

it('refuses to delete a fund with journal entries', function () {
    JournalEntry::post(['account_code' => '1010', 'fund_code' => 'GENERAL', 'description' => 'x', 'debit_cents' => 100, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->admin)
        ->delete('/admin/funds/GENERAL')
        ->assertRedirect();

    expect(Fund::where('code', 'GENERAL')->exists())->toBeTrue();
});

it('deletes an unused fund', function () {
    Fund::create(['code' => 'TEMP', 'name' => 'Temp', 'fund_type' => 'general']);

    $this->actingAs($this->admin)
        ->delete('/admin/funds/TEMP')
        ->assertRedirect();

    expect(Fund::where('code', 'TEMP')->exists())->toBeFalse();
});

it('gates writes behind accounting.post_journal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/admin/funds', [
            'code' => 'NOPE',
            'name' => 'Nope',
            'fund_type' => 'general',
        ])
        ->assertForbidden();
});
