<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_card_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('equipment_sku')->nullable();
            $table->string('unit'); // hourly, daily, multi_day, time_slot, per_unit, flat
            $table->unsignedBigInteger('rate_cents');
            $table->unsignedBigInteger('min_charge_cents')->default(0);
            $table->unsignedSmallInteger('included_hours')->nullable();
            $table->jsonb('conditions_json')->nullable();
            $table->timestamps();

            $table->index(['rate_card_id', 'space_id']);
            $table->index(['rate_card_id', 'equipment_sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_card_entries');
    }
};
