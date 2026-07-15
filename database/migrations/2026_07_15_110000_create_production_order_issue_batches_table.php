<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_issue_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_issue_id')
                ->constrained('production_order_issues')
                ->cascadeOnDelete();

            $table->foreignId('inventory_batch_id')
                ->nullable()
                ->constrained('batches')
                ->nullOnDelete();

            $table->decimal('quantity', 12, 4);

            $table->foreignId('stock_transaction_id')
                ->nullable()
                ->constrained('stock_transactions')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_issue_id'], 'poi_batches_tenant_issue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_issue_batches');
    }
};
