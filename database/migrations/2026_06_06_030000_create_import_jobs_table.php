<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic data-import jobs. One job = one uploaded file of one
 * "kind" (clients, chart-of-accounts, bookings, ...) moving through the
 * upload -> map -> preview -> commit pipeline. Source-agnostic: the mapping
 * from the file's columns to Velsa fields lives in column_map, so a Momentus
 * migration is just a saved mapping, not special-case code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->index();
            $table->string('status')->default('pending')->index();
            $table->string('original_filename');
            $table->string('disk')->default('local');
            $table->string('file_path');
            $table->boolean('has_header')->default(true);
            $table->string('delimiter', 4)->default(',');
            // target_field => source column header (or index when headerless).
            $table->json('column_map')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            // Forward hook for the read-only/maintenance gate: whether the
            // environment was read-only for the whole commit (reversal-safety).
            $table->boolean('read_only_covered')->default(false);
            $table->json('summary_json')->nullable();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
