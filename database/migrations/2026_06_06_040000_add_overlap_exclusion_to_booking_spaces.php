<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level backstop against double-booking. The application
 * already rejects overlapping blocking bookings in BookingSpace's save hooks,
 * but that can't stop a race between two concurrent confirmations or a direct
 * DB write. This adds a Postgres GIST exclusion constraint: no two
 * booking_spaces on the same space whose parent booking is in a blocking
 * status (definite/completed) may overlap in time.
 *
 * `blocks_overlap` denormalizes the parent booking's blocking status onto the
 * row (a partial-index predicate can't join to bookings). It's kept in sync
 * by BookingSpace's save hook and Booking's status-change hook.
 *
 * The constraint uses raw event windows; setup/teardown buffer overlaps stay
 * application-enforced (they depend on a per-venue flag). The constraint is
 * Postgres-only - on SQLite (tests) the column exists but the GIST exclusion
 * is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_spaces', function (Blueprint $table) {
            $table->boolean('blocks_overlap')->default(false)->after('teardown_minutes_after');
        });

        // Backfill from current booking statuses.
        DB::table('booking_spaces')
            ->whereIn('booking_id', fn ($q) => $q->select('id')
                ->from('bookings')
                ->whereIn('status', ['definite', 'completed']))
            ->update(['blocks_overlap' => true]);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // btree_gist lets a GIST index mix the integer `space_id =` operator
        // with the range `&&` overlap operator.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE booking_spaces
            ADD CONSTRAINT booking_spaces_no_blocking_overlap
            EXCLUDE USING gist (
                space_id WITH =,
                tsrange(start_at, end_at, '[)') WITH &&
            ) WHERE (blocks_overlap)
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE booking_spaces DROP CONSTRAINT IF EXISTS booking_spaces_no_blocking_overlap');
        }

        Schema::table('booking_spaces', function (Blueprint $table) {
            $table->dropColumn('blocks_overlap');
        });
    }
};
