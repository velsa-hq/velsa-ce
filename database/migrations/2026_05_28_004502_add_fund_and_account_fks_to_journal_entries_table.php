<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promote the existing string account_code / fund_code columns on
 * journal_entries to validated FK references against chart_of_accounts
 * and funds. The string columns stay as denormalized copies (kept in
 * sync by the JournalEntry model) so the export-template renderer
 * keeps working without a join.
 *
 * restrictOnDelete: a journal entry referencing an account or fund
 * locks them in place - they can be retired (active_to) but never
 * hard-deleted. Same pattern as ledger_export_batches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->after('account_code')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('fund_id')
                ->nullable()
                ->after('fund_code')
                ->constrained('funds')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_of_account_id');
            $table->dropConstrainedForeignId('fund_id');
        });
    }
};
