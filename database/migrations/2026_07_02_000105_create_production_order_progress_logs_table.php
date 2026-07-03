<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('operation_id')
                ->constrained('production_order_operations')
                ->cascadeOnDelete();

            $table->decimal('quantity_produced', 12, 4)->default(0.0000);
            $table->decimal('quantity_rejected', 12, 4)->default(0.0000);
            $table->decimal('quantity_scrapped', 12, 4)->default(0.0000);

            $table->decimal('setup_minutes_logged', 10, 2)->default(0.00);
            $table->decimal('run_minutes_logged', 10, 2)->default(0.00);

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            
            $table->foreignId('machine_id')->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();

            $table->string('remarks')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_prog_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_progress_logs');
    }
};
