<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            // A Booking has at most one PaymentSchedule
            // containing one or more Installments. Generalizes the
            // existing 2-phase deposit + balance flow into N phases.
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            // Cached sum of installments - kept in sync by
            // PaymentScheduleService so callers don't have to re-sum
            // the installments to render a booking row.
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
