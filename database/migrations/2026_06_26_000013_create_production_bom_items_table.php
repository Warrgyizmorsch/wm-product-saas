<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('production_boms')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->foreignId('uom_id')->constrained('uoms')->cascadeOnDelete();
            $table->decimal('wastage_percentage', 5, 2)->default(0.00);
            $table->boolean('is_alternative')->default(false);
            $table->string('alternative_group')->nullable();
            $table->integer('sequence')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'bom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_bom_items');
    }
};
