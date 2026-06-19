<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('business');
            $table->string('industry')->nullable();
            $table->string('source')->nullable();
            $table->jsonb('address_json')->nullable();
            $table->text('tax_id_encrypted')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('custom_fields_json')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
