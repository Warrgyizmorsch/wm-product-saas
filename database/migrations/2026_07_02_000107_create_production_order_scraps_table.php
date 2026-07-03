<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_scraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('production_order_operation_id')->nullable()
                ->constrained('production_order_operations')
                ->nullOnDelete();

            // product_id is nullable if scrapping parent target item, otherwise foreign key for component material
            $table->foreignId('product_id')->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->decimal('quantity', 12, 4);
            $table->string('reason')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_scr_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_scraps');
    }
};
