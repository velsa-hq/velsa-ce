<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_space_id')->nullable()->constrained('spaces')->restrictOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sqft')->nullable();
            $table->jsonb('dimensions_json')->nullable();
            $table->string('bookable_unit');
            $table->jsonb('attributes_json')->nullable();
            $table->json('constraints_json')->nullable();
            $table->timestamp('retired_at')->nullable()->index();
            $table->timestamps();

            $table->index(['venue_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
