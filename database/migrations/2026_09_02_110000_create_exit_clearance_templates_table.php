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
        if (!Schema::hasTable('exit_clearance_templates')) {
            Schema::create('exit_clearance_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->string('clearance_category'); // e.g., it_assets, facilities_admin, finance_payroll, hr_operations, reporting_manager, legal_compliance
                $table->string('category_name');      // e.g., "IT & Systems", "Facilities & Admin", "Finance & Payroll"
                $table->string('item_name');          // e.g., "Hardware Asset Recovery (Laptop/Accessories)"
                $table->text('description')->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->integer('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_clearance_templates');
    }
};
