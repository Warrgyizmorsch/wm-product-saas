<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_cost_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('production_order_id')->index();
            $table->date('adjustment_date')->index();
            $table->string('cost_component', 50); // material, labor, machine, overhead, other
            $table->string('category', 100);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->string('attachment_path', 255)->nullable();
            $table->string('status', 30)->default('recorded');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('production_order_id')
                ->references('id')
                ->on('production_orders')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_cost_adjustments');
    }
};
