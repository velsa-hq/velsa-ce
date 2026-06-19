<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acknowledgements exhibitors record against a venue's published handbook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_handbook_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['exhibitor_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_handbook_acknowledgements');
    }
};
