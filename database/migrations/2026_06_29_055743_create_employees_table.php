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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            
            // Organization Structure

            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('business_unit_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('branch_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('department_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('designation_id')
                  ->constrained()
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Basic Details

            $table->string('employee_id')->unique();
            $table->string('full_name');
            $table->string('nick_name')->nullable();

            $table->enum('blood_group',[
                'A+','A-',
                'B+','B-',
                'AB+','AB-',
                'O+','O-'
            ])->nullable();

            $table->string('employee_stage')->nullable();
            $table->string('job_title')->nullable();
            $table->string('role')->nullable();
            $table->string('employment_type')->nullable();
            $table->date('date_of_joining');
            $table->string('office')->nullable();

            $table->enum('gender',[
                'Male',
                'Female',
                'Other'
            ]);

            $table->enum('marital_status',[
                'Single',
                'Married',
                'Divorced',
                'Widowed'
            ])->nullable();

            $table->enum('diet_preference',[
                'Veg',
                'Non Veg',
                'Vegan'
            ])->nullable();

            $table->string('aadhaar_card_number',20)->nullable()->unique();
            $table->string('pan_card_number',20)->nullable()->unique();
            $table->string('photo')->nullable();

            // Contact Details
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code',20)->nullable();
            $table->string('personal_mobile_number',20)->nullable();
            $table->string('home_phone',20)->nullable();
            $table->string('personal_email')->nullable()->unique();

            // Professional Details
            $table->decimal('experience',5,2)->default(0);
            $table->string('source_of_hire')->nullable();
            $table->text('skill_set')->nullable();
            $table->decimal('current_salary',12,2)->default(0);
            $table->string('qualification')->nullable();

            // Status
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
