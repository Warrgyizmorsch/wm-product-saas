<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_eco_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('eco_id')->index();

            $table->string('entity_type', 30); // BOM, ROUTING
            $table->string('action_type', 50); // ADD_COMPONENT, REMOVE_COMPONENT, REPLACE_COMPONENT, CHANGE_QUANTITY, CHANGE_SCRAP_FACTOR, ADD_OPERATION, REMOVE_OPERATION, CHANGE_OPERATION_SEQUENCE, CHANGE_WORK_CENTER, CHANGE_MACHINE, CHANGE_SETUP_TIME, CHANGE_RUN_TIME

            $table->unsignedBigInteger('target_id')->nullable(); // product_id or routing_operation_id
            $table->text('old_value')->nullable(); // JSON
            $table->text('new_value')->nullable(); // JSON
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('eco_id')->references('id')->on('production_ecos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_eco_items');
    }
};
