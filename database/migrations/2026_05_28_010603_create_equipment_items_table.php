<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment / service master catalog - the canonical list of items
 * rentable to exhibitors at a venue.
 *
 * Each item belongs to a category (which carries the default GL
 * coding); item-level overrides exist for one-offs that need
 * different accounts than their category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_category_id')->nullable()->constrained('equipment_categories')->nullOnDelete();
            $table->string('sku', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit_label', 20)->default('each'); // each, day, hour, sqft
            $table->unsignedInteger('unit_price_cents')->default(0);
            $table->unsignedBigInteger('advance_price_cents')->nullable();
            $table->string('debit_account_code', 32)->nullable();  // override if non-null
            $table->string('credit_account_code', 32)->nullable(); // override if non-null
            $table->decimal('tax_rate', 5, 4)->nullable();          // override if non-null
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_items');
    }
};
