<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity_total')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->boolean('is_consumable')->default(false);
            $table->jsonb('attributes_json')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['venue_id', 'sku']);
            $table->index(['venue_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_inventories');
    }
};
