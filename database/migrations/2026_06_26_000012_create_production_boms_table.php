<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('bom_number');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('version')->default('1.0.0');
            $table->integer('revision')->default(0);
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('draft'); // draft, pending_approval, approved, inactive
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->unique(['tenant_id', 'bom_number', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_boms');
    }
};
