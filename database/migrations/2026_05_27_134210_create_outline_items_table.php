<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outline_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_outline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('department')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_outline_id', 'scheduled_at']);
            $table->index(['department', 'scheduled_at']);
            $table->index('responsible_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outline_items');
    }
};
