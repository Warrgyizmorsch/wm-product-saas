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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            // Business Unit Relationship
            $table->foreignId('business_unit_id')
                  ->constrained('business_units')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Branch Details
            $table->string('name');
            $table->string('code')->unique();

            // Branch Manager (Employee)
            $table->unsignedBigInteger('manager_employee_id')->nullable();

            // Contact Details
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Status
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
