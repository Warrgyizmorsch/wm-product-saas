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
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('employee_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('asset_category_id')
                  ->constrained()
                  ->restrictOnDelete();
            
            $table->text('reason');
            $table->date('request_date');
            $table->enum('status', ['pending', 'approved', 'rejected', 'allocated'])
                  ->default('pending');
            
            $table->foreignId('allocated_asset_id')
                  ->nullable()
                  ->constrained('assets')
                  ->nullOnDelete();
            
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
    }
};
