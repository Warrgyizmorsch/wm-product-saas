<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->decimal('quantity_received', 12, 4);
            $table->string('quality_status')->default('passed'); // passed, quarantine, failed

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at');
            $table->string('remarks')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_rec_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_receipts');
    }
};
