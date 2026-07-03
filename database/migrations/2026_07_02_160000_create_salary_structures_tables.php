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
        if (!Schema::hasTable('salary_structures')) {
            Schema::create('salary_structures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->decimal('min_ctc', 15, 2)->default(0.00);
                $table->decimal('max_ctc', 15, 2);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('salary_structure_items')) {
            Schema::create('salary_structure_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('salary_structure_id');
                $table->unsignedBigInteger('salary_component_id');
                $table->string('calculation_type'); // fixed, percentage_of_ctc, percentage_of_basic, balancing
                $table->decimal('value', 15, 2)->default(0.00);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('salary_structure_id')->references('id')->on('salary_structures')->cascadeOnDelete();
                $table->foreign('salary_component_id')->references('id')->on('salary_components')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_structure_items');
        Schema::dropIfExists('salary_structures');
    }
};
