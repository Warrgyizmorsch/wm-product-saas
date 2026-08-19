<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create biometric_devices table
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('device_serial');
            $table->string('ip_address')->nullable();
            $table->integer('port')->default(4370);
            $table->boolean('status')->default(true);
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'device_serial']);
        });

        // 3. Create biometric_punch_logs table
        Schema::create('biometric_punch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->dateTime('punch_time');
            $table->string('punch_type')->default('auto'); // in, out, break_in, break_out, auto
            $table->boolean('processed')->default(false);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'punch_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punch_logs');
        Schema::dropIfExists('biometric_devices');
    }
};
