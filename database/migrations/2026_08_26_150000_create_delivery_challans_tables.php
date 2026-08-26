<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('challan_number');
            $table->string('type')->default('subcontract_dispatch');
            $table->unsignedBigInteger('production_order_id')->nullable()->index();
            $table->unsignedBigInteger('production_order_operation_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->date('challan_date');
            $table->date('expected_return_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('vehicle_number')->nullable();
            $table->string('transporter_name')->nullable();
            $table->string('lr_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'challan_number']);
        });

        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('delivery_challan_id')->constrained('delivery_challans')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('quantity', 12, 4);
            $table->string('unit_of_measure')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
    }
};
