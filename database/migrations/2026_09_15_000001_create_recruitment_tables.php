<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Job Requisitions Table
        Schema::create('job_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('requisition_code')->unique();
            $table->string('job_title');
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('designation_id')->nullable()->index();
            $table->integer('vacancies')->default(1);
            $table->unsignedSmallInteger('min_experience_years')->default(0);
            $table->unsignedSmallInteger('max_experience_years')->default(5);
            $table->string('work_mode')->default('onsite'); // onsite, remote, hybrid
            $table->string('job_location')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, part_time, contract, internship
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('status')->default('pending_approval'); // draft, pending_approval, approved, published, closed, cancelled
            $table->unsignedBigInteger('requested_by_employee_id')->nullable()->index();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('skills_required')->nullable();
            $table->longText('job_description')->nullable();
            $table->date('target_joining_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Candidates Table
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('candidate_code')->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('current_location')->nullable();
            $table->string('current_company')->nullable();
            $table->string('current_designation')->nullable();
            $table->unsignedSmallInteger('total_experience_years')->default(0);
            $table->integer('notice_period_days')->default(30);
            $table->string('resume_path')->nullable();
            $table->string('source')->default('direct'); // direct, referral, linkedin, agency, other
            $table->string('source_details')->nullable();
            $table->string('status')->default('active'); // active, hired, rejected, blacklisted
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Candidate Applications Table (Tracking status per requisition)
        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('candidate_id')->index();
            $table->unsignedBigInteger('job_requisition_id')->index();
            $table->string('current_stage')->default('applied'); 
            // Stage values: applied, screening, interview_round_1, interview_round_2, interview_round_3, final_hr, offer_sent, hired, rejected
            $table->timestamp('stage_updated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('candidate_id')->references('id')->on('candidates')->onDelete('cascade');
            $table->foreign('job_requisition_id')->references('id')->on('job_requisitions')->onDelete('cascade');
        });

        // 4. Candidate Interviews Table
        Schema::create('candidate_interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->index();
            $table->integer('round_number')->default(1);
            $table->string('round_name')->default('Interview Round');
            $table->dateTime('scheduled_at');
            $table->unsignedBigInteger('interviewer_employee_id')->nullable()->index();
            $table->string('meeting_link')->nullable();
            $table->string('venue_location')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled, rescheduled
            $table->text('round_notes')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('candidate_applications')->onDelete('cascade');
        });

        // 5. Interview Scorecards Table
        Schema::create('interview_scorecards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('interview_id')->index();
            $table->unsignedBigInteger('interviewer_user_id')->nullable();
            $table->unsignedTinyInteger('technical_rating')->default(3); // 1 to 5
            $table->unsignedTinyInteger('communication_rating')->default(3); // 1 to 5
            $table->unsignedTinyInteger('culture_fit_rating')->default(3); // 1 to 5
            $table->unsignedTinyInteger('overall_rating')->default(3); // 1 to 5
            $table->string('recommendation')->default('pass'); // pass, hold, reject
            $table->text('feedback_notes')->nullable();
            $table->timestamps();

            $table->foreign('interview_id')->references('id')->on('candidate_interviews')->onDelete('cascade');
        });

        // 6. Job Offers Table
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->index();
            $table->string('offer_code')->unique();
            $table->unsignedBigInteger('offered_designation_id')->nullable();
            $table->unsignedBigInteger('offered_department_id')->nullable();
            $table->decimal('offered_annual_ctc', 12, 2)->default(0.00);
            $table->date('joining_date')->nullable();
            $table->text('offer_letter_notes')->nullable();
            $table->string('status')->default('draft'); // draft, sent, accepted, declined
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('converted_employee_id')->nullable();
            $table->timestamps();

            $table->foreign('application_id')->references('id')->on('candidate_applications')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_scorecards');
        Schema::dropIfExists('candidate_interviews');
        Schema::dropIfExists('candidate_applications');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_requisitions');
    }
};
