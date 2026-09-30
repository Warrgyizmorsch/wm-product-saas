<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('log_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours', 8, 2);
            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->text('description')->nullable();
            // Approval status: Pending, Approved, Rejected
            $table->string('approval_status')->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_remarks')->nullable();
            $table->boolean('is_invoiced')->default(false);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'approval_status']);
            $table->index(['tenant_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_logs');
    }
};
