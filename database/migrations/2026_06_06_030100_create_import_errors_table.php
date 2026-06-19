<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row failures for an import job - produced both on preview (dry run)
 * and on commit. Carries the original row verbatim so the operator can fix
 * and re-import just the failures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field')->nullable();
            $table->text('message');
            $table->json('raw_row_json')->nullable();
            $table->timestamps();

            $table->index(['import_job_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
