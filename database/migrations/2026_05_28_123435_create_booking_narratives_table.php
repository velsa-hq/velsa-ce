<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-booking event narrative - a chronological diary staff append
     * to over the life of the booking. Different from `bookings.notes`
     * (a single overwritable field) and different from the audit log
     * (which tracks model mutations, not human narrative).
     *
     * Append-only by convention: no admin UI to edit or delete an
     * entry after the fact. Mis-typed entries get a correction entry
     * appended; the original stays put so the history is honest.
     */
    public function up(): void
    {
        Schema::create('booking_narratives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('kind', 32)->default('note');
            $table->text('body');
            $table->timestamp('happened_at');
            $table->timestamps();

            // Booking-scoped chronological queries are the primary
            // access pattern (newest-first on the show page).
            $table->index(['booking_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_narratives');
    }
};
