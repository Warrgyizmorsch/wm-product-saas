<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for 360-Degree Multi-Rater Feedback.
     */
    public function up(): void
    {
        // 1. Feedback 360 Cycles
        if (!Schema::hasTable('feedback_360_cycles')) {
            Schema::create('feedback_360_cycles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->index();
                $table->text('description')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->date('nomination_deadline')->nullable();
                $table->date('submission_deadline')->nullable();
                $table->enum('status', ['draft', 'nomination', 'in_progress', 'review', 'completed', 'closed'])->default('draft');
                $table->boolean('is_peer_anonymous')->default(true);
                $table->boolean('is_direct_report_anonymous')->default(true);
                $table->unsignedSmallInteger('min_peer_nominations')->default(2);
                $table->unsignedSmallInteger('max_peer_nominations')->default(5);
                $table->boolean('allow_self_nomination')->default(true);
                $table->boolean('require_manager_approval')->default(true);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 2. Feedback 360 Competencies Library
        if (!Schema::hasTable('feedback_360_competencies')) {
            Schema::create('feedback_360_competencies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('category')->default('Core Competencies');
                $table->text('description')->nullable();
                $table->decimal('weightage', 5, 2)->default(100.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                $table->foreign('cycle_id')->references('id')->on('feedback_360_cycles')->onDelete('cascade');
            });
        }

        // 3. Feedback 360 Questions Bank
        if (!Schema::hasTable('feedback_360_questions')) {
            Schema::create('feedback_360_questions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('competency_id')->nullable()->index();
                $table->unsignedBigInteger('cycle_id')->nullable()->index();
                $table->text('question_text');
                $table->text('description')->nullable();
                $table->enum('question_type', ['rating_scale', 'text'])->default('rating_scale');
                $table->enum('target_reviewer_type', ['all', 'self', 'manager', 'peer', 'direct_report'])->default('all');
                $table->boolean('is_required')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('competency_id')->references('id')->on('feedback_360_competencies')->onDelete('set null');
                $table->foreign('cycle_id')->references('id')->on('feedback_360_cycles')->onDelete('cascade');
            });
        }

        // 4. Feedback 360 Participants (Reviewees / Subjects in a cycle)
        if (!Schema::hasTable('feedback_360_participants')) {
            Schema::create('feedback_360_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('cycle_id')->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->unsignedBigInteger('manager_id')->nullable()->index();
                $table->enum('status', ['nomination_pending', 'in_progress', 'completed', 'published'])->default('nomination_pending');
                $table->decimal('self_score', 4, 2)->nullable();
                $table->decimal('manager_score', 4, 2)->nullable();
                $table->decimal('peer_score', 4, 2)->nullable();
                $table->decimal('direct_report_score', 4, 2)->nullable();
                $table->decimal('overall_score', 4, 2)->nullable();
                $table->text('manager_summary')->nullable();
                $table->text('development_plan')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('cycle_id')->references('id')->on('feedback_360_cycles')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
                $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
                $table->unique(['cycle_id', 'employee_id']);
            });
        }

        // 5. Feedback 360 Nominations (Individual Rater Assignments)
        if (!Schema::hasTable('feedback_360_nominations')) {
            Schema::create('feedback_360_nominations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('cycle_id')->index();
                $table->unsignedBigInteger('participant_id')->index();
                $table->unsignedBigInteger('employee_id')->index(); // Reviewee subject
                $table->unsignedBigInteger('reviewer_id')->index(); // Reviewer / Rater
                $table->enum('reviewer_type', ['self', 'manager', 'peer', 'direct_report', 'external'])->default('peer');
                $table->enum('status', ['pending_approval', 'approved', 'rejected', 'in_progress', 'completed', 'declined'])->default('approved');
                $table->boolean('is_anonymous')->default(false);
                $table->unsignedBigInteger('nominated_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->text('nomination_reason')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('cycle_id')->references('id')->on('feedback_360_cycles')->onDelete('cascade');
                $table->foreign('participant_id')->references('id')->on('feedback_360_participants')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('reviewer_id')->references('id')->on('employees')->onDelete('cascade');
                $table->unique(['cycle_id', 'participant_id', 'reviewer_id', 'reviewer_type'], 'f360_nom_unique');
            });
        }

        // 6. Feedback 360 Response Items
        if (!Schema::hasTable('feedback_360_responses')) {
            Schema::create('feedback_360_responses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('nomination_id')->index();
                $table->unsignedBigInteger('question_id')->index();
                $table->unsignedBigInteger('competency_id')->nullable()->index();
                $table->decimal('rating_value', 4, 2)->nullable();
                $table->text('text_response')->nullable();
                $table->timestamps();

                $table->foreign('nomination_id')->references('id')->on('feedback_360_nominations')->onDelete('cascade');
                $table->foreign('question_id')->references('id')->on('feedback_360_questions')->onDelete('cascade');
                $table->foreign('competency_id')->references('id')->on('feedback_360_competencies')->onDelete('set null');
                $table->unique(['nomination_id', 'question_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_360_responses');
        Schema::dropIfExists('feedback_360_nominations');
        Schema::dropIfExists('feedback_360_participants');
        Schema::dropIfExists('feedback_360_questions');
        Schema::dropIfExists('feedback_360_competencies');
        Schema::dropIfExists('feedback_360_cycles');
    }
};
