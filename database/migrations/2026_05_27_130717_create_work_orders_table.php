<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exhibitor_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exhibitor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('work_order_templates')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('kind')->index();
            $table->string('department')->nullable();
            $table->string('status')->default('open')->index();
            $table->unsignedTinyInteger('priority')->default(3); // 1 = critical, 5 = backlog
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('cost_cents')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'status', 'scheduled_for']);
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['template_id', 'scheduled_for']);
            $table->index(['exhibitor_order_id', 'department']);
        });

        // One open, generated work order per (exhibitor order, department):
        // a partial unique index so completed/cancelled rows don't collide.
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX work_orders_open_order_department_unique
                ON work_orders (exhibitor_order_id, department)
                WHERE exhibitor_order_id IS NOT NULL
                  AND status NOT IN ('completed', 'cancelled')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
