<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->string('status')->default('inquiry')->index();
            $table->string('hold_rank')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->decimal('deposit_percent', 5, 2)->default(50.00);
            $table->unsignedInteger('attendance_estimate')->nullable();
            $table->unsignedInteger('attendance_actual')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'status', 'start_at']);
            $table->index(['start_at', 'end_at']);
            $table->index('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
