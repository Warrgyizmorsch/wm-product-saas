<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('duration', 4, 1); // e.g. 0.5, 1.0, 2.5
            $table->string('start_date_type')->default('full_day'); // full_day, first_half, second_half
            $table->string('end_date_type')->default('full_day'); // full_day, first_half, second_half
            $table->json('notified_contacts')->nullable(); // notified employee IDs
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->string('current_level')->default('1'); // 1, 2, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
