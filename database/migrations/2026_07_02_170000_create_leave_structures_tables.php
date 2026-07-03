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
        if (!Schema::hasTable('leave_plans')) {
            Schema::create('leave_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->date('effective_from');
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('leave_plan_id');
                $table->string('name');
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('type'); // paid, unpaid
                $table->string('color')->default('#3b82f6');
                $table->decimal('quota', 5, 1)->default(0.0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->foreign('leave_plan_id')->references('id')->on('leave_plans')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('leave_plans');
    }
};
