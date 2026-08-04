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
        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('company_id'); // Legal Entity
            $table->unsignedBigInteger('business_unit_id')->nullable(); // Optional Business Unit
            $table->unsignedBigInteger('branch_id')->nullable(); // Optional Branch
            
            // Office Settings
            $table->boolean('office_biometric')->default(false);
            $table->boolean('office_web')->default(false);
            $table->string('office_latitude')->nullable();
            $table->string('office_longitude')->nullable();
            $table->integer('office_radius')->default(100); // Allowed range in meters

            // WFH Settings
            $table->boolean('wfh_location')->default(false);
            $table->boolean('wfh_selfie')->default(false);
            $table->boolean('wfh_geofence')->default(false);
            $table->boolean('wfh_tracking')->default(false);
            $table->integer('wfh_tracking_meters')->default(50);

            // On-Site Settings
            $table->boolean('site_location')->default(false);
            $table->boolean('site_selfie')->default(false);
            $table->boolean('site_geofence')->default(false);
            $table->boolean('site_tracking')->default(false);
            $table->integer('site_tracking_meters')->default(50);

            $table->boolean('status')->default(true);
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('business_unit_id')->references('id')->on('business_units')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};
