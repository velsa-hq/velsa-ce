<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outline_item_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('department')->nullable();
            $table->unsignedSmallInteger('default_duration_minutes')->default(30);
            $table->text('description')->nullable();
            // Ordered list of checklist task labels, materialized into
            // outline_item_tasks when an item is created from this template.
            $table->json('checklist')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outline_item_templates');
    }
};
