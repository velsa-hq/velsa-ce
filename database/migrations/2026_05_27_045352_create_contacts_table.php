<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->jsonb('preferences_json')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['client_id', 'is_primary']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('primary_contact_id')->nullable()->after('source')
                ->constrained('contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_contact_id');
        });

        Schema::dropIfExists('contacts');
    }
};
