<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of Accounts.
 *
 * Single CoA shared across all Funds (the standard government model)
 * - JournalEntry rows tag both account_code and fund_code so per-
 * fund AND per-account reporting works.
 *
 * Hierarchy via parent_account_id (e.g. 1000 Cash > 1010 Cash-Op,
 * 1020 Cash-Reserve). Non-postable parents serve as roll-up
 * categories; only is_postable accounts may appear on journal
 * entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('account_type', 20)->index();   // asset | liability | equity | revenue | expense
            $table->string('account_subtype', 60)->nullable();
            $table->string('normal_balance', 6);            // debit | credit
            $table->foreignId('parent_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_postable')->default(true)->index();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
