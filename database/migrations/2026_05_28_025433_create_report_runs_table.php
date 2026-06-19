<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Execution log for user-defined reports. One row per run; lets us
 * surface "last run" + parameters in the admin UI and build a
 * caching layer later without changing the table shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')
                ->constrained('report_definitions')
                ->cascadeOnDelete();
            $table->json('params_json')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('summary_json')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
