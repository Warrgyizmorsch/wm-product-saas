<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_routing_operation_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('routing_operation_id');
            $table->foreign('routing_operation_id', 'rom_routing_op_fk')
                ->references('id')
                ->on('production_routing_operations')
                ->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->string('consumption_type')->nullable(); // e.g. backflush, manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_routing_operation_materials');
    }
};
