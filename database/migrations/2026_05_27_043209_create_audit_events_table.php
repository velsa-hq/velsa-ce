<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log.
 *
 * - Records session events, model changes, sensitive-record access,
 *   exports, prints, permission changes, payment events
 * - user_id and venue_id are NULLABLE: unauthenticated events (e.g. a
 *   failed login attempt with no resolvable user) still get a row
 * - payload_json carries before/after snapshots for model changes, or
 *   context for session/access events
 * - On Postgres a trigger blocks UPDATE and DELETE on this table at the
 *   DB layer - defense-in-depth even if the app code is compromised
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            // no FK cascade: the audit log is append-only and immutable, so a
            // user/venue delete must not nullify these (the trigger below blocks
            // UPDATE anyway). The historical id is retained.
            $table->foreignId('user_id')->nullable();
            $table->foreignId('venue_id')->nullable();
            $table->string('event_type')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('payload_json')->nullable();
            $table->timestamp('created_at', 6)->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['venue_id', 'event_type', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_events_block_mutation()
                RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_events is append-only; % is not permitted', TG_OP
                        USING ERRCODE = '42501';
                END;
                $$;

                CREATE TRIGGER audit_events_no_update
                    BEFORE UPDATE ON audit_events
                    FOR EACH ROW EXECUTE FUNCTION audit_events_block_mutation();

                CREATE TRIGGER audit_events_no_delete
                    BEFORE DELETE ON audit_events
                    FOR EACH ROW EXECUTE FUNCTION audit_events_block_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update ON audit_events');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete ON audit_events');
            DB::unprepared('DROP FUNCTION IF EXISTS audit_events_block_mutation()');
        }

        Schema::dropIfExists('audit_events');
    }
};
