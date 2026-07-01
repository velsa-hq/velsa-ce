<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cryptographic integrity protection for the audit trail (STIG APSC-DV-001350 /
 * NIST AU-9). Each row carries an HMAC-SHA256 over a canonical serialization of
 * its immutable fields, keyed by an APP_KEY-derived sub-key; `audit:verify-integrity`
 * recomputes and compares it to detect out-of-band tampering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('integrity_hash', 64)->nullable()->after('payload_json');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn('integrity_hash');
        });
    }
};
