<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('signing_order')->default(1);
            $table->string('role')->default('client');
            $table->string('name');
            $table->string('email');
            $table->string('provider_recipient_id')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'signing_order']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signers');
    }
};
