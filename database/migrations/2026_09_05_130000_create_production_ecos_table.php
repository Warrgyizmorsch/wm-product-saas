<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_ecos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('eco_number', 50)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('reason')->nullable();
            $table->string('change_type', 50); // BOM_CHANGE, ROUTING_CHANGE, BOM_AND_ROUTING_CHANGE
            $table->unsignedBigInteger('product_id')->index();

            $table->unsignedBigInteger('current_bom_id')->nullable();
            $table->unsignedBigInteger('proposed_bom_id')->nullable();
            $table->integer('current_bom_revision')->default(0);
            $table->integer('proposed_bom_revision')->default(1);

            $table->unsignedBigInteger('current_routing_id')->nullable();
            $table->unsignedBigInteger('proposed_routing_id')->nullable();
            $table->integer('current_routing_revision')->default(0);
            $table->integer('proposed_routing_revision')->default(1);

            $table->date('effective_date')->nullable();
            $table->string('status', 30)->default('draft')->index(); // draft, under_review, approved, released, rejected, cancelled, closed

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'eco_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_ecos');
    }
};
