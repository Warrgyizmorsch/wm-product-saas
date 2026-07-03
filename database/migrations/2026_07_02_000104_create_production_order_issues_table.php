<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->cascadeOnDelete();

            $table->foreignId('reservation_id')
                ->constrained('production_order_reservations')
                ->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->decimal('quantity_issued', 12, 4);
            $table->string('issue_type')->default('standard'); // standard, additional, return

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->string('remarks')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'production_order_id'], 'po_iss_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_issues');
    }
};
