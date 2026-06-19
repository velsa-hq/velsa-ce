<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit windows where a Venue or Space is unavailable
     * for booking ("Convention Center HVAC maintenance Aug 4-6"). The
     * polymorphic shape lets a single Blackout block an entire venue OR
     * just one space - venue-wide blackouts cascade to all child spaces;
     * space blackouts cascade to partition children via the parent_space
     * tree in the conflict-check service.
     */
    public function up(): void
    {
        Schema::create('blackouts', function (Blueprint $table) {
            $table->id();
            $table->morphs('blackoutable');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            // Conflict-check query filters on (blackoutable, time window).
            $table->index(['blackoutable_type', 'blackoutable_id', 'starts_at', 'ends_at'], 'blackouts_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blackouts');
    }
};
