<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagram_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagram_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('objects_json');
            $table->string('thumbnail_s3_key')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->unique(['diagram_id', 'version']);
        });

        // diagrams.current_version_id reference stays at the Eloquent layer.
        // A SQLite ALTER TABLE here would force a table recreate that loses
        // the prior columns; a Postgres-only FK migration can come later
        // when we set up a Postgres-backed test suite.
    }

    public function down(): void
    {
        Schema::dropIfExists('diagram_versions');
    }
};
