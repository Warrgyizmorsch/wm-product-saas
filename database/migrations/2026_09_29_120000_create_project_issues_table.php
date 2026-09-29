<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('issue_number', 32);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 32)->default('Medium');
            $table->string('severity', 32)->default('Major');
            $table->string('status', 32)->default('Open');
            $table->timestamp('resolution_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('retest_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'project_id', 'issue_number']);
            $table->index(['tenant_id', 'project_id', 'status']);
            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_issues');
    }
};
