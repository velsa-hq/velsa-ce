<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entra group -> Spatie role mappings.
 *
 * Drives the role-assignment side of the SSO flow: when a user signs in
 * via Microsoft Entra, we fetch their group memberships and look up
 * these mappings to decide which role(s) to assign at which venue(s).
 *
 * One row = one (entra_group_id, role_name, venue_id) tuple. venue_id
 * is nullable - null means "assign at every active venue".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entra_group_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entra_group_id'); // Entra group GUID
            $table->string('group_label')->nullable(); // human-readable, captured at create time
            $table->string('role_name'); // FK by name to spatie roles
            $table->foreignId('venue_id')->nullable()
                ->constrained('venues')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Lookup by group id is hot on every SSO sign-in.
            $table->index('entra_group_id');
            // Prevent dupes - same group, same role, same venue scope.
            $table->unique(
                ['entra_group_id', 'role_name', 'venue_id'],
                'entra_group_role_mapping_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entra_group_role_mappings');
    }
};
