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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            // Branch Relationship
            $table->foreignId('branch_id')
                  ->constrained('branches')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Department Details
            $table->string('name');
            $table->string('code');

            // Department Head (Employee)
            $table->unsignedBigInteger('head_employee_id')->nullable();

            // Description
            $table->text('description')->nullable();

            // Status
            $table->boolean('status')->default(true);

            $table->timestamps();

            // Unique Code per Branch
            $table->unique(['branch_id', 'code']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
