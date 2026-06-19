<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget lines: one row per (fiscal_year x account x
 * fund). Fund is nullable for cross-fund / unrestricted budgets.
 * Actuals are NOT stored - they're computed from journal_entries on
 * read via BudgetService so the budgeted-vs-actual variance always
 * reflects the current ledger state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->restrictOnDelete();
            // Signed bigint to allow contra-account budgets if needed.
            $table->bigInteger('amount_cents')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One budget line per accountxfundxyear - re-running the
            // editor updates the existing row rather than appending.
            $table->unique(['fiscal_year_id', 'chart_of_account_id', 'fund_id'], 'budgets_unique_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
