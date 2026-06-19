<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('cart')->index();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['exhibitor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_orders');
    }
};
