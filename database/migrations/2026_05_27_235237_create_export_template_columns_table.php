<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_template_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_template_id')->constrained('export_templates')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label');                  // CSV header / fixed-width field name
            $table->string('source');                 // see ExportSource catalog
            $table->string('format_mask')->nullable(); // 'date:Y-m-d', 'money:dot', 'pad-zero:6', etc.
            $table->string('default_value')->nullable();
            $table->unsignedInteger('width')->nullable(); // fixed_width formats only
            $table->string('align', 8)->default('left');  // fixed_width only: left | right
            $table->string('pad_char', 1)->default(' ');  // fixed_width only
            $table->timestamps();

            $table->index(['export_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_template_columns');
    }
};
