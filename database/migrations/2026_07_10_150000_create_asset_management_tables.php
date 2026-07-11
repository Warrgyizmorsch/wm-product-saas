<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('asset_category_id')
                  ->constrained()
                  ->restrictOnDelete();
            
            // Asset details
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            
            // Financial details
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            
            // Physical Condition
            $table->enum('condition', ['new', 'good', 'fair', 'damaged', 'scrapped'])
                  ->default('good');
            
            // Allocation status details
            $table->enum('status', ['available', 'allocated', 'maintenance', 'scrapped'])
                  ->default('available');
            $table->foreignId('assigned_employee_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete();
            $table->date('allocated_at')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('employee_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->date('allocated_at');
            $table->date('returned_at')->nullable();
            $table->string('allocation_condition');
            $table->string('return_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_allocations');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
