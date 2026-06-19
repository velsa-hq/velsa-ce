<?php

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** balanced two-line payload (debit 1010 / credit 4200) */
function balancedLines(int $cents = 5000): array
{
    return [
        ['account_code' => '1010', 'debit_cents' => $cents, 'credit_cents' => 0],
        ['account_code' => '4200', 'debit_cents' => 0, 'credit_cents' => $cents],
    ];
}

it('posts a balanced manual journal entry as grouped legs', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)
        ->post('/accounting/journal', [
            'description' => 'Year-end accrual',
            'lines' => balancedLines(5000),
        ])
        ->assertSessionHas('toast.type', 'success');

    $legs = JournalEntry::query()->where('description', 'Year-end accrual')->get();
    expect($legs)->toHaveCount(2)
        ->and($legs->pluck('entry_group')->unique())->toHaveCount(1)
        ->and($legs->first()->entry_group)->not->toBeNull()
        ->and($legs->sum('debit_cents'))->toBe(5000)
        ->and($legs->sum('credit_cents'))->toBe(5000)
        ->and($legs->pluck('posted_by_user_id')->unique()->first())->toBe($admin->id)
        ->and($legs->pluck('export_batch_id')->unique()->first())->toBeNull(); // flows into next export
});

it('rejects an unbalanced entry', function () {
    $this->actingAs(grantSuperAdmin())
        ->post('/accounting/journal', [
            'description' => 'Lopsided',
            'lines' => [
                ['account_code' => '1010', 'debit_cents' => 5000, 'credit_cents' => 0],
                ['account_code' => '4200', 'debit_cents' => 0, 'credit_cents' => 4000],
            ],
        ])
        ->assertSessionHasErrors('lines');

    expect(JournalEntry::query()->where('description', 'Lopsided')->exists())->toBeFalse();
});

it('rejects a line that is both a debit and a credit', function () {
    $this->actingAs(grantSuperAdmin())
        ->post('/accounting/journal', [
            'description' => 'Both',
            'lines' => [
                ['account_code' => '1010', 'debit_cents' => 5000, 'credit_cents' => 5000],
                ['account_code' => '4200', 'debit_cents' => 0, 'credit_cents' => 5000],
            ],
        ])
        ->assertSessionHasErrors('lines.0');
});

it('blocks posting into a closed fiscal year', function () {
    FiscalYear::query()->create([
        'label' => 'FY2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'is_closed' => true,
    ]);

    $this->actingAs(grantSuperAdmin())
        ->post('/accounting/journal', [
            'posted_on' => '2026-06-15',
            'description' => 'Into closed year',
            'lines' => balancedLines(),
        ])
        ->assertSessionHasErrors('posted_on');

    expect(JournalEntry::query()->where('description', 'Into closed year')->exists())->toBeFalse();
});

it('requires the accounting.post_journal permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRoleAt(Venue::factory()->create(), 'sales_rep'); // no accounting perms

    $this->actingAs($user)
        ->post('/accounting/journal', ['description' => 'Nope', 'lines' => balancedLines()])
        ->assertForbidden();
});

it('reverses a manual entry by posting the mirror legs', function () {
    $admin = grantSuperAdmin();
    $this->actingAs($admin)->post('/accounting/journal', [
        'description' => 'Reclass',
        'lines' => balancedLines(8000),
    ]);
    $leg = JournalEntry::query()->where('description', 'Reclass')->first();

    $this->actingAs($admin)
        ->post("/accounting/journal/{$leg->id}/reverse")
        ->assertSessionHas('toast.type', 'success');

    $reversal = JournalEntry::query()->where('description', 'Reversal: Reclass')->get();
    expect($reversal)->toHaveCount(2)
        // debits/credits swapped vs the original
        ->and($reversal->sum('debit_cents'))->toBe(8000)
        ->and($reversal->sum('credit_cents'))->toBe(8000)
        ->and($reversal->whereNotNull('reversed_entry_id'))->toHaveCount(2);

    // a second reverse is refused
    $this->actingAs($admin)
        ->post("/accounting/journal/{$leg->id}/reverse")
        ->assertSessionHas('toast.type', 'error');
});

it('does not reverse a system-generated entry', function () {
    $system = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'System cash',
        'debit_cents' => 1000,
    ]);

    $this->actingAs(grantSuperAdmin())
        ->post("/accounting/journal/{$system->id}/reverse")
        ->assertStatus(422);
});
