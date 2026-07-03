<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('order_number');
            $table->foreignId('production_plan_id')->nullable()->constrained('production_plans')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            $table->foreignId('bom_id')->nullable()->constrained('production_boms')->nullOnDelete();
            $table->foreignId('routing_id')->nullable()->constrained('routings')->nullOnDelete();

            $table->decimal('quantity_ordered', 12, 4);
            $table->decimal('quantity_produced', 12, 4)->default(0.0000);
            $table->decimal('quantity_rejected', 12, 4)->default(0.0000);
            $table->decimal('quantity_scrapped', 12, 4)->default(0.0000);

            $table->date('start_date');
            $table->date('end_date');
            $table->dateTime('actual_start_date')->nullable();
            $table->dateTime('actual_end_date')->nullable();

            $table->string('status')->default('draft');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('released_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'order_number'], 'production_orders_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
