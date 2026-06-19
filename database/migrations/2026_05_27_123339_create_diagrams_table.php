<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagrams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_version_id')->nullable();
            $table->string('name');
            $table->float('scale_px_per_foot')->default(10);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagrams');
    }
};
