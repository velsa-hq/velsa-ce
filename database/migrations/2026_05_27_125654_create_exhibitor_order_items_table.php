<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_order_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('gl_account')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();

            $table->index(['exhibitor_order_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_order_items');
    }
};
