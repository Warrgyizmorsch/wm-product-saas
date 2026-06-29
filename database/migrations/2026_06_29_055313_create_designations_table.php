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
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            // Department Relationship
            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Designation Details
            $table->string('name');
            $table->string('level')->nullable();
            $table->text('description')->nullable();

            // Status
            $table->boolean('status')->default(true);

            $table->timestamps();

            // Prevent duplicate designation names in the same department
            $table->unique(['department_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
