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
        if (!Schema::hasTable('salary_components')) {
            Schema::create('salary_components', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->string('code');
                $table->string('type'); // earning, deduction
                $table->string('calculation_type'); // fixed, percentage, formula
                $table->string('default_value')->nullable(); // default value or formula expression
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                // Foreign key constraints
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
