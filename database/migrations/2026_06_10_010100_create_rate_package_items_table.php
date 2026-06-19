<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The components included in a package/bundle. Each line is a space, a piece
 * of catalogue equipment, or a free-text service, with a quantity. Pricing is
 * at the package level (rate_packages.price_cents) - these lines document what
 * the bundle contains, not per-line rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->string('equipment_sku')->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('rate_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_package_items');
    }
};
