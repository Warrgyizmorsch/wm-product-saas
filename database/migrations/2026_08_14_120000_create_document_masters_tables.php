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
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('document_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('document_category_id')
                  ->constrained('document_categories')
                  ->restrictOnDelete();
            
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            
            // Document Configuration
            $table->boolean('is_required')->default(false);
            $table->string('upload_responsibility')->default('employee'); // employee, hr, both
            $table->boolean('approval_required')->default(false);
            
            // Expiry Configuration
            $table->boolean('expiry_applicable')->default(false);
            $table->integer('reminder_days_before')->nullable();
            
            // Employee Access
            $table->boolean('employee_can_view')->default(false);
            $table->boolean('employee_can_download')->default(false);
            
            // Status
            $table->string('status')->default('active'); // active, inactive
            
            $table->timestamps();

            // Ensure document code is unique per tenant
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_masters');
        Schema::dropIfExists('document_categories');
    }
};
