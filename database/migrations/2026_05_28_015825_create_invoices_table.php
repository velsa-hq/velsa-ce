<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts receivable invoices.
 *
 * Polymorphic `invoiceable` so the same model serves both exhibitor
 * orders and booking deposits/balances. Totals are snapshotted from
 * the source at issuance time - once an invoice is issued, downstream
 * edits to the source order don't retroactively change the bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('invoiceable_type', 120);
            $table->unsignedBigInteger('invoiceable_id');
            $table->index(['invoiceable_type', 'invoiceable_id']);

            $table->string('status', 24)->index();
            $table->string('dunning_stage', 24)->default('none')->index();

            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            // Denormalized for fast aging queries; kept in sync via service.
            $table->unsignedBigInteger('paid_cents')->default(0);

            // Issuance / due / sent / paid / voided timestamps drive
            // statement and dunning behavior.
            $table->date('issued_on')->nullable()->index();
            $table->date('due_on')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('revenue_posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            // Payment terms snapshot. Defaults to Net 30; future:
            // configurable per client / per booking type.
            $table->unsignedSmallInteger('net_days')->default(30);

            // Optional manual notes that appear on the invoice document.
            $table->text('notes')->nullable();
            $table->string('customer_reference')->nullable();
            $table->string('internal_reference')->nullable();

            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
