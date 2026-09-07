<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_eco_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('eco_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('action', 50); // Created, Submitted, Approved, Rejected, Released, Closed, Cancelled
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->foreign('eco_id')->references('id')->on('production_ecos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_eco_approvals');
    }
};
