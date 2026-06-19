<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-defined report definitions. Each definition picks
 * a datasource (bookings, exhibitor_orders, etc.), applies filters,
 * groups by dimensions, and aggregates metrics. ReportRun rows track
 * each execution. Definitions are surfaced through the same
 * ReportRegistry as the hardcoded named reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('datasource', 60)->index();      // see ReportDatasource enum
            // JSON-stored builder config:
            //   filters_json     => [{field, operator, value}, ...]
            //   dimensions_json  => ['field1', 'field2']
            //   metrics_json     => [{field, aggregation, label}]
            //   sort_json        => [{field, direction}]
            $table->json('filters_json')->nullable();
            $table->json('dimensions_json')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('sort_json')->nullable();
            $table->unsignedInteger('row_limit')->default(1000);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
