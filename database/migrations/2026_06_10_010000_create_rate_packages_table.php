<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package / bundle pricing: a named offering sold at a single bundled price
 * that includes multiple components (space rental + equipment + services).
 * Parallels rate_cards (venue-scoped, effective-dated, by kind) but carries a
 * package-level price rather than per-line rates - the bundle is the unit sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('standard');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('price_cents');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'kind', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_packages');
    }
};
