<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app support requests. A signed-in user submits a question, problem, or
 * suggestion; the request is recorded here (so nothing is lost if email is
 * down) and optionally emailed to the configured support recipients. Admins
 * triage the open/closed status from Admin -> Support requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('subject');
            $table->text('body');
            $table->string('page_url')->nullable();
            $table->string('app_version')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
