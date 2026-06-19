<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exhibitor_event_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('booth_assignment')->nullable();
            $table->string('booth_size')->nullable();
            $table->jsonb('address_json')->nullable();
            $table->string('magic_token')->unique()->nullable();
            $table->timestamp('magic_token_expires_at')->nullable();
            $table->timestamps();

            $table->index(['exhibitor_event_id', 'booth_assignment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitors');
    }
};
