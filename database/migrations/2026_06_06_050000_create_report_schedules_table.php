<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled report delivery: a saved report (slug + captured
 * filter params) emailed to recipients on a cadence in a chosen format.
 * The bookings:* / reports:dispatch-scheduled command renders + sends due
 * schedules; last_run_at guards against double-sends within a period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_slug')->index();
            $table->json('params_json')->nullable();
            $table->string('format')->default('pdf'); // csv | xlsx | pdf
            $table->string('frequency'); // daily | weekly | monthly
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0-6 (weekly)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-28 (monthly)
            $table->unsignedTinyInteger('hour')->default(6); // 0-23, local
            $table->json('recipients'); // list of email addresses
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
