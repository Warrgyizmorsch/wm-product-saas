<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('plan_number');
            $table->string('name');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            $table->foreignId('bom_id')->nullable()
                ->constrained('production_boms')
                ->nullOnDelete();
                
            $table->foreignId('routing_id')->nullable()
                ->constrained('routings')
                ->nullOnDelete();

            $table->decimal('quantity', 12, 4)->default(1.0000);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'plan_number'], 'production_plans_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plans');
    }
};
