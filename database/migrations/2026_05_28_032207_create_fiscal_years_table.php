<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal year. Many government agencies run an Oct 1 -> Sep 30
 * fiscal year; stored as start/end dates so non-calendar fiscal
 * years from other deployments work without special-casing.
 *
 * is_closed: once a fiscal year is closed, no new journal entries
 * may post into it (enforced at the application layer in
 * JournalEntry::saving - wired in a follow-up if needed; for now
 * the flag is purely informational).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('label', 16)->unique();    // e.g. "FY26", "FY2026"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_closed')->default(false)->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
