<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layout_templates', function (Blueprint $table) {
            $table->id();
            // Nullable space_id = "global" template available everywhere.
            // Otherwise the template is scoped to a single space and only
            // surfaces in that space's editor.
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            // List of placed objects in template form. Stored exactly
            // like DiagramVersion.objects_json - same `id/type/x/y/rotation/props`
            // shape - so applying a template is a clone of these rows
            // into the active diagram.
            $table->json('objects_json');
            // Captured at save-time for the browse UI; pure hint, no enforcement.
            $table->unsignedSmallInteger('object_count')->default(0);
            $table->unsignedSmallInteger('seat_count')->default(0);
            $table->timestamps();

            $table->index(['space_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layout_templates');
    }
};
