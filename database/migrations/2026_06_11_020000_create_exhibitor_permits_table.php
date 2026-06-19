<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_id')->constrained()->cascadeOnDelete();
            $table->string('permit_type');
            $table->text('details');
            $table->string('status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('submitted_via_portal')->default(false);
            $table->timestamps();

            $table->index(['exhibitor_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_permits');
    }
};
