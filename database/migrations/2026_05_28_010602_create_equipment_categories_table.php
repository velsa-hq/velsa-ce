<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories for the equipment master.
 *
 * Each category encodes the GL coding that will be applied when an
 * item from that category is sold - debit account (typically AR),
 * credit account (revenue), and the applicable tax rate. The default
 * is overridden per-item only when the item's GL coding differs from
 * the category default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('department', 40)->nullable()->index(); // catering, av, ops, etc.
            $table->string('debit_account_code', 32)->nullable();  // typically 1100 AR
            $table->string('credit_account_code', 32)->nullable(); // revenue account
            $table->decimal('tax_rate', 5, 4)->default(0);          // e.g. 0.0700 for Florida 7%
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_categories');
    }
};
