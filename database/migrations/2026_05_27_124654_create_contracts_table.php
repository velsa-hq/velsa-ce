<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->foreignId('parent_contract_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('kind')->default('contract');
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->text('rendered_html')->nullable();
            $table->string('pdf_s3_key')->nullable();

            // E-signature provider
            $table->string('provider')->default('docusign');
            $table->string('provider_envelope_id')->nullable()->index();

            // Life-cycle timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('decline_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Self-FK for addenda chained off their parent contract.
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->cascadeOnDelete();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
