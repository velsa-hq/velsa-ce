<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Starter chart of accounts for event-management operations.
 * Idempotent on `code`; parent links resolve in a second pass so
 * array order doesn't matter.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ---- ASSETS (1000s) ----
            ['code' => '1000', 'name' => 'Assets', 'account_type' => AccountType::Asset->value, 'is_postable' => false],
            ['code' => '1010', 'name' => 'Cash - Operating', 'account_type' => AccountType::Asset->value, 'account_subtype' => 'cash', 'parent' => '1000'],
            ['code' => '1020', 'name' => 'Cash - Reserve', 'account_type' => AccountType::Asset->value, 'account_subtype' => 'cash', 'parent' => '1000'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'account_type' => AccountType::Asset->value, 'account_subtype' => 'receivable', 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Prepaid Expenses', 'account_type' => AccountType::Asset->value, 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Inventory - Resources & Equipment', 'account_type' => AccountType::Asset->value, 'account_subtype' => 'inventory', 'parent' => '1000'],

            // ---- LIABILITIES (2000s) ----
            ['code' => '2000', 'name' => 'Liabilities', 'account_type' => AccountType::Liability->value, 'is_postable' => false],
            ['code' => '2010', 'name' => 'Accounts Payable', 'account_type' => AccountType::Liability->value, 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Deposits Payable - Client', 'account_type' => AccountType::Liability->value, 'account_subtype' => 'deposit', 'parent' => '2000', 'description' => 'Refundable client deposits held against future events.'],
            ['code' => '2110', 'name' => 'Deposits Payable - Exhibitor', 'account_type' => AccountType::Liability->value, 'account_subtype' => 'deposit', 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Sales Tax Payable', 'account_type' => AccountType::Liability->value, 'parent' => '2000'],
            ['code' => '2300', 'name' => 'Deferred Revenue', 'account_type' => AccountType::Liability->value, 'parent' => '2000'],

            // ---- EQUITY (3000s) ----
            ['code' => '3000', 'name' => 'Fund Balance', 'account_type' => AccountType::Equity->value, 'is_postable' => false],
            ['code' => '3010', 'name' => 'Fund Balance - Unassigned', 'account_type' => AccountType::Equity->value, 'parent' => '3000'],
            ['code' => '3020', 'name' => 'Fund Balance - Restricted', 'account_type' => AccountType::Equity->value, 'parent' => '3000'],

            // ---- REVENUE (4000s) ----
            ['code' => '4000', 'name' => 'Revenue', 'account_type' => AccountType::Revenue->value, 'is_postable' => false],
            ['code' => '4100', 'name' => 'Venue Rental Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4200', 'name' => 'Convention & Event Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000', 'description' => 'Booking fees from convention center and large-event clients.'],
            ['code' => '4300', 'name' => 'Exhibitor Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4400', 'name' => 'Catering & F&B Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4500', 'name' => 'AV & Equipment Rental Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4600', 'name' => 'RV / Campsite Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4700', 'name' => 'Stall Rental Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],
            ['code' => '4900', 'name' => 'Other / Misc Revenue', 'account_type' => AccountType::Revenue->value, 'parent' => '4000'],

            // ---- EXPENSES (5000s) ----
            ['code' => '5000', 'name' => 'Expenses', 'account_type' => AccountType::Expense->value, 'is_postable' => false],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Utilities', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Repairs & Maintenance', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5400', 'name' => 'Supplies & Materials', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5500', 'name' => 'Contracted Services', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5600', 'name' => 'Marketing & Promotion', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5700', 'name' => 'Merchant Processing Fees', 'account_type' => AccountType::Expense->value, 'parent' => '5000', 'description' => 'BluePay and credit-card processing fees.'],
            ['code' => '5800', 'name' => 'Insurance', 'account_type' => AccountType::Expense->value, 'parent' => '5000'],
            ['code' => '5900', 'name' => 'Bad Debt Expense', 'account_type' => AccountType::Expense->value, 'parent' => '5000', 'description' => 'Written-off uncollectable AR balances.'],
        ];

        // pass 1: upsert rows without parent linkage
        foreach ($accounts as $row) {
            $payload = $row;
            unset($payload['parent']);
            ChartOfAccount::query()->updateOrCreate(
                ['code' => $row['code']],
                $payload,
            );
        }

        // pass 2: stitch parents now that every row exists
        foreach ($accounts as $row) {
            if (! isset($row['parent'])) {
                continue;
            }
            $parent = ChartOfAccount::query()->where('code', $row['parent'])->first();
            $child = ChartOfAccount::query()->where('code', $row['code'])->first();
            if ($parent !== null && $child !== null && $child->parent_account_id !== $parent->id) {
                $child->forceFill(['parent_account_id' => $parent->id])->save();
            }
        }
    }
}
