<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_cents');
            $table->string('label')->nullable();
            // Filled in by the daily IssueDueInstallments command once
            // due_date is reached. nullOnDelete because voiding an
            // invoice from the admin shouldn't cascade-delete the
            // installment row - it just frees it up to be invoiced
            // again on the next run.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_schedule_id', 'sequence']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
