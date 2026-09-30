<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Enterprise SOP (Standard Operating Procedure) Management.
     */
    public function up(): void
    {
        // 1. SOP Categories Master Table
        if (!Schema::hasTable('sop_categories')) {
            Schema::create('sop_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('color')->default('#3b82f6');
                $table->string('icon')->default('feather-file-text');
                $table->text('description')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            });
        }

        // 2. SOP Documents Main Master Table
        if (!Schema::hasTable('sop_documents')) {
            Schema::create('sop_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('sop_category_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('code')->index();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->longText('objective')->nullable();
                $table->longText('scope')->nullable();
                $table->text('prerequisites')->nullable();
                $table->string('version')->default('1.0');
                $table->enum('status', ['draft', 'under_review', 'published', 'archived'])->default('draft');
                $table->enum('criticality', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->enum('target_audience_type', ['all', 'department', 'designation', 'custom'])->default('all');
                $table->json('target_department_ids')->nullable();
                $table->json('target_designation_ids')->nullable();
                $table->json('target_employee_ids')->nullable();
                $table->boolean('is_mandatory')->default(true);
                $table->boolean('auto_assign_new_hires')->default(true);
                $table->integer('acknowledgment_days_limit')->default(7);
                $table->date('effective_date')->nullable();
                $table->integer('review_interval_months')->default(12);
                $table->date('next_review_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('sop_category_id')->references('id')->on('sop_categories')->onDelete('set null');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 3. SOP Sections / Steps Table (Rich step-by-step procedures)
        if (!Schema::hasTable('sop_sections')) {
            Schema::create('sop_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('sop_document_id')->index();
                $table->integer('step_number')->default(1);
                $table->string('title');
                $table->longText('content');
                $table->boolean('has_checklist')->default(false);
                $table->json('checklist_items')->nullable();
                $table->text('guidelines')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamps();

                $table->foreign('sop_document_id')->references('id')->on('sop_documents')->onDelete('cascade');
            });
        }

        // 4. SOP Assignments & Acknowledgments Table (Compliance tracking & digital sign-off)
        if (!Schema::hasTable('sop_assignments')) {
            Schema::create('sop_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('sop_document_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('version_assigned')->default('1.0');
                $table->enum('status', ['pending', 'acknowledged', 'overdue'])->default('pending');
                $table->boolean('is_mandatory')->default(true);
                $table->timestamp('assigned_at')->useCurrent();
                $table->date('due_date')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->text('signature_data')->nullable();
                $table->json('checklist_responses')->nullable();
                $table->timestamp('last_reminded_at')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('sop_document_id')->references('id')->on('sop_documents')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }

        // 5. SOP Version History & Audit Log Table
        if (!Schema::hasTable('sop_version_histories')) {
            Schema::create('sop_version_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('sop_document_id')->index();
                $table->string('version');
                $table->enum('change_type', ['minor', 'major'])->default('minor');
                $table->text('changes_summary');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->json('snapshot_data')->nullable();
                $table->timestamps();

                $table->foreign('sop_document_id')->references('id')->on('sop_documents')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sop_version_histories');
        Schema::dropIfExists('sop_assignments');
        Schema::dropIfExists('sop_sections');
        Schema::dropIfExists('sop_documents');
        Schema::dropIfExists('sop_categories');
    }
};
