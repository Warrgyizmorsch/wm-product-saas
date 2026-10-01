<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_review_id')->nullable()->constrained('project_reviews')->nullOnDelete();
            $table->string('cr_number');
            $table->string('title');
            $table->text('description');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('impact_schedule_days')->default(0);
            $table->decimal('impact_budget_amount', 15, 2)->default(0.00);
            $table->decimal('impact_budget_hours', 10, 2)->default(0.00);
            $table->string('status')->default('Pending')->index(); // Pending, Approved, Rejected, Implemented
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'cr_number'], 'project_crs_tenant_cr_number_unique');
            $table->index(['tenant_id', 'project_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('project_review_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_change_requests');
    }
};
