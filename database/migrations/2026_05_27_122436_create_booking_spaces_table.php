<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedSmallInteger('setup_minutes_before')->default(0);
            $table->unsignedSmallInteger('teardown_minutes_after')->default(0);
            $table->unsignedBigInteger('rate_applied_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'start_at', 'end_at']);
            $table->index(['booking_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_spaces');
    }
};
