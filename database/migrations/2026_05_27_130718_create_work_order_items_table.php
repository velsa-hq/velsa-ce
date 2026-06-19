<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exhibitor_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit')->nullable();
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->string('action');
            $table->timestamp('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'action']);
            $table->index('resource_inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
    }
};
