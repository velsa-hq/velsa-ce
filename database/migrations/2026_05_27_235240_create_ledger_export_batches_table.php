<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_export_batches', function (Blueprint $table) {
            $table->id();
            $table->string('period')->index(); // e.g. 2026-05
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('entry_count')->default(0);
            $table->unsignedBigInteger('debit_total_cents')->default(0);
            $table->unsignedBigInteger('credit_total_cents')->default(0);

            // Restrict on delete: a template referenced by a historical export
            // cannot be hard-deleted, which protects audit traceability.
            $table->foreignId('export_template_id')
                ->nullable()
                ->constrained('export_templates')
                ->restrictOnDelete();

            $table->string('file_s3_key')->nullable();
            $table->string('delivery_transport')->nullable();
            $table->string('delivery_detail')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_export_batches');
    }
};
