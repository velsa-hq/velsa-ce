<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('portal_slug')->unique();
            $table->string('default_booth_size')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('advance_rate_deadline')->nullable();
            $table->unsignedSmallInteger('late_order_surcharge_pct')->default(0);
            $table->jsonb('settings_json')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'registration_closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_events');
    }
};
