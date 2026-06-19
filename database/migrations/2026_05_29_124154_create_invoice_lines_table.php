<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-invoice line items. Bookings have historically invoiced as a
 * single total; this gives every invoice (regardless of source) a
 * canonical line shape so PDFs / XLSX / future statements can render
 * itemized detail and finance can attach per-line cost codes.
 *
 * Backfilled from existing data in the sibling migration
 * `backfill_invoice_lines_from_sources`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('description');
            $table->string('detail')->nullable(); // sub-line context (e.g. "10 x $5.00")
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_price_cents')->default(0);
            $table->integer('line_total_cents')->default(0); // denormalised = qty x unit_price
            $table->string('reference')->nullable(); // per-line cost code / GL account
            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
