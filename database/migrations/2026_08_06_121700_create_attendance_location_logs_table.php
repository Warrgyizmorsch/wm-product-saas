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
        if (!Schema::hasTable('attendance_location_logs')) {
            Schema::create('attendance_location_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('attendance_id');
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->timestamp('created_at')->nullable();

                $table->foreign('attendance_id')->references('id')->on('attendances')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_location_logs');
    }
};
