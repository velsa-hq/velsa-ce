<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order lines now reference the equipment master. sku, name, and
 * pricing fields are kept as denormalized copies so order history
 * survives catalog renames and price changes (the standard
 * accounting/POS pattern). FK is nullSafe so existing free-text
 * order lines aren't broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhibitor_order_items', function (Blueprint $table) {
            $table->foreignId('equipment_item_id')
                ->nullable()
                ->after('exhibitor_order_id')
                ->constrained('equipment_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exhibitor_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_item_id');
        });
    }
};
