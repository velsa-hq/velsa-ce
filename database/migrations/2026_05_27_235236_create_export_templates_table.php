<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable journal export templates. One template maps to one
 * output file shape; the export driver picks a template by id at export time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('format'); // csv | fixed_width (xml in v2)
            $table->string('delimiter', 4)->default(',');
            $table->string('quote_char', 2)->default('"');
            $table->string('line_ending', 4)->default("\n");
            $table->string('encoding', 32)->default('utf-8');
            $table->boolean('include_header')->default(true);
            $table->boolean('include_footer')->default(false);
            $table->boolean('is_default')->default(false)->index();
            $table->string('file_extension', 8)->default('csv');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_templates');
    }
};
