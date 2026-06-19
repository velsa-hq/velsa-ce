<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('stage')->default('new')->index();
            $table->unsignedBigInteger('estimated_value_cents')->default(0);
            $table->decimal('probability', 4, 3)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->string('source')->nullable();
            $table->string('lost_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['venue_id', 'stage']);
            $table->index(['owner_user_id', 'stage']);
            $table->index('expected_close_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
