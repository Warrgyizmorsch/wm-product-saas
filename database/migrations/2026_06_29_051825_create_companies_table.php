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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Organization Relationship
            $table->foreignId('organization_id')
                  ->constrained('organizations')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Basic Information
            $table->string('company_name');
            $table->string('legal_name')->nullable();

            // Registration Details
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('cin_number')->nullable();
            $table->string('registration_number')->nullable();

            // Contact Details
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();

            // Company Logo
            $table->string('logo')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Regional Settings
            $table->string('currency', 10)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');

            // Status
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
