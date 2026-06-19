<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only journal entries: the operational system-of-record for the
 * ledger. Entries are pushed to the external accounting system via
 * LedgerExportBatch.
 *
 * A Postgres-only defense-in-depth trigger blocks UPDATE/DELETE at the
 * DB layer. Reversals are modeled as new entries pointing to
 * reversed_entry_id, never as edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('reversed_entry_id')->nullable();
            $table->uuid('entry_group')->nullable()->index();
            $table->foreignId('export_batch_id')->nullable();
            $table->string('account_code')->index();
            $table->string('fund_code')->nullable()->index();
            $table->string('description');
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->date('posted_on')->index();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['source_type', 'source_id']);
            $table->index(['venue_id', 'posted_on']);
            $table->index(['account_code', 'posted_on']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION journal_entries_block_mutation()
                RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    -- Allow only export_batch_id to be set after creation. Block
                    -- every other UPDATE plus all DELETEs.
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'journal_entries is append-only; DELETE is not permitted' USING ERRCODE = '42501';
                    END IF;
                    IF TG_OP = 'UPDATE' THEN
                        IF NEW.account_code <> OLD.account_code
                            OR NEW.debit_cents <> OLD.debit_cents
                            OR NEW.credit_cents <> OLD.credit_cents
                            OR NEW.description <> OLD.description
                            OR NEW.posted_on <> OLD.posted_on THEN
                            RAISE EXCEPTION 'journal_entries content is immutable; create a reversing entry instead' USING ERRCODE = '42501';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$;

                CREATE TRIGGER journal_entries_no_mutation
                    BEFORE UPDATE OR DELETE ON journal_entries
                    FOR EACH ROW EXECUTE FUNCTION journal_entries_block_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS journal_entries_no_mutation ON journal_entries');
            DB::unprepared('DROP FUNCTION IF EXISTS journal_entries_block_mutation()');
        }

        Schema::dropIfExists('journal_entries');
    }
};
