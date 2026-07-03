<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plan_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('production_plan_id')
                ->constrained('production_plans')
                ->cascadeOnDelete();

            $table->foreignId('bom_item_id')->nullable()
                ->constrained('production_bom_items')
                ->nullOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('bom_level');
            $table->decimal('required_quantity', 12, 4);
            $table->decimal('available_quantity', 12, 4)->default(0.0000);
            $table->decimal('reserved_quantity', 12, 4)->default(0.0000);
            $table->decimal('shortage_quantity', 12, 4)->default(0.0000);

            $table->foreignId('uom_id')->constrained('uoms');
            
            $table->foreignId('source_item_id')->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'production_plan_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_requirements');
    }
};
