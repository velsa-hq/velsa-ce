<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger of exactly which records a committed import created, so a reversal
 * deletes only those - never cascading into unrelated data. Polymorphic so
 * one job can create rows across several tables (e.g. a client + its primary
 * contact).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->morphs('importable');
            $table->unsignedInteger('row_number');
            $table->timestamps();

            $table->index('import_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_records');
    }
};
