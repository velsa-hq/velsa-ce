<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('bluepay');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->char('last4', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_payments');
    }
};
