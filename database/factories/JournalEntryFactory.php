<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Venue;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 *
 * Tests/seeders only - production entries post through the accounting services.
 * The saving hook resolves `account_code` against the chart of accounts and
 * throws if missing, so seed {@see ChartOfAccountsSeeder} first.
 */
class JournalEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => null,
            'account_code' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->orderBy('code')
                ->value('code'),
            'fund_code' => null,
            'description' => fake()->sentence(3),
            'debit_cents' => fake()->numberBetween(1_000, 500_000),
            'credit_cents' => 0,
            'posted_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    public function forAccount(string $code): static
    {
        return $this->state(fn (): array => ['account_code' => $code]);
    }

    public function atVenue(Venue|int $venue): static
    {
        return $this->state(fn (): array => [
            'venue_id' => $venue instanceof Venue ? $venue->id : $venue,
        ]);
    }

    public function credit(int $cents): static
    {
        return $this->state(fn (): array => [
            'debit_cents' => 0,
            'credit_cents' => $cents,
        ]);
    }
}
