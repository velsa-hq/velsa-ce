<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency (break-glass) accounts may never be disabled or deleted while the
 * flag is set, so the operator always retains an administrative path back in.
 * STIG APSC-DV-000310 (CM-3 / emergency-account protection).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_emergency')->default(false)->after('disabled_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_emergency');
        });
    }
};
