<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('recurrence_rrule')->nullable();
            $table->jsonb('items_json')->nullable();
            $table->string('default_assignee_role')->nullable();
            $table->unsignedSmallInteger('lookahead_days')->default(14);
            $table->timestamp('last_materialized_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_templates');
    }
};
