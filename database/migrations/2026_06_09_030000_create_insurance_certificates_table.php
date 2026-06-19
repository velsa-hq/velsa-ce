<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates of Insurance (COI) tracked against a polymorphic holder - a
 * Client (the renting organization) or an Exhibitor. Carries the coverage
 * details, a review decision, and an expiry date the nightly job watches.
 * The certificate document itself is stored via spatie/laravel-medialibrary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('holder_type');
            $table->unsignedBigInteger('holder_id');
            $table->string('policy_type');
            $table->string('carrier')->nullable();
            $table->string('policy_number')->nullable();
            $table->unsignedBigInteger('coverage_amount_cents')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expires_on');
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('submitted_via_portal')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id']);
            $table->index(['status', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_certificates');
    }
};
