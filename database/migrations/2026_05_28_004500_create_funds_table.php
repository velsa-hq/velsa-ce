<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Government / multi-entity fund accounting. Each Fund is a separate
 * pool of money (General, Enterprise, Capital Projects, etc.) with
 * its own books, but they share a common chart of accounts and
 * calendar. Every JournalEntry is tagged with a Fund so per-fund
 * balance sheets and income statements are possible without
 * splitting the chart of accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('fund_type', 40)->index();
            $table->text('description')->nullable();
            $table->foreignId('parent_fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
