<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_assignments', function (Blueprint $table) {
            // Shift-level "who is working what role on
            // this event". Distinct from OutlineItem.responsible_user_id
            // - that's per-task ownership; this is per-shift roster.
            // The outline editor uses these as the candidate pool when
            // picking a responsible user.
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedBigInteger('hourly_rate_cents')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'start_at']);
            // A user can take multiple shifts on the same booking
            // (split shifts) so no uniqueness constraint here.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
