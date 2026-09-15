<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RecruitmentController extends Controller
{
    // Main Recruitment Entry Point (Redirects to Job Requisitions)
    public function index(): RedirectResponse
    {
        return redirect()->route('hrms.recruitment.requisitions.index');
    }

    // Requisitions List with Standard Search, Sort & Filter
    public function requisitions(Request $request): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $filters = [
            'search'        => $request->get('search', ''),
            'department_id' => $request->get('department_id', ''),
            'status'        => $request->get('status', ''),
            'sort'          => $request->get('sort', 'date_desc'),
        ];

        $query = JobRequisition::with(['department', 'designation', 'requestedBy', 'applications'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('job_title', 'like', "%{$search}%")
                  ->orWhere('requisition_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        switch ($filters['sort']) {
            case 'title_asc':
                $query->orderBy('job_title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('job_title', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'vacancies_desc':
                $query->orderBy('vacancies', 'desc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $requisitions = $query->paginate(10)->appends($filters);
        $departments = Department::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $designations = Designation::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $employees = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();

        $interviewsQuery = CandidateInterview::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'scheduled');
        $upcomingInterviews = $interviewsQuery->with(['application.candidate', 'application.requisition', 'interviewer'])
            ->orderBy('scheduled_at', 'asc')
            ->take(5)
            ->get();

        return view('modules.hrms.recruitment.requisitions', compact('requisitions', 'departments', 'designations', 'employees', 'filters', 'upcomingInterviews'));
    }

    // Store Job Requisition
    public function storeRequisition(Request $request): RedirectResponse
    {
        $request->validate([
            'job_title'             => 'required|string|max:255',
            'department_id'         => 'required|exists:departments,id',
            'designation_id'        => 'required|exists:designations,id',
            'vacancies'             => 'required|integer|min:1',
            'min_experience_years'  => 'required|integer|min:0',
            'max_experience_years'  => 'required|integer|gte:min_experience_years',
            'work_mode'             => 'required|in:onsite,remote,hybrid',
            'employment_type'       => 'required|in:full_time,part_time,contract,internship',
            'priority'              => 'required|in:low,medium,high,urgent',
            'job_location'          => 'nullable|string|max:255',
            'skills_required'       => 'nullable|string',
            'job_description'       => 'nullable|string',
            'target_joining_date'   => 'nullable|date',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $reqCount = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
        $code = 'REQ-' . date('Y') . '-' . str_pad($reqCount, 3, '0', STR_PAD_LEFT);

        JobRequisition::create([
            'tenant_id'                 => $tenantId,
            'requisition_code'          => $code,
            'job_title'                 => $request->job_title,
            'department_id'             => $request->department_id,
            'designation_id'            => $request->designation_id,
            'vacancies'                 => $request->vacancies,
            'min_experience_years'      => $request->min_experience_years,
            'max_experience_years'      => $request->max_experience_years,
            'work_mode'                 => $request->work_mode,
            'employment_type'           => $request->employment_type,
            'priority'                  => $request->priority,
            'job_location'              => $request->job_location,
            'skills_required'           => $request->skills_required,
            'job_description'           => $request->job_description,
            'target_joining_date'       => $request->target_joining_date,
            'status'                    => 'approved',
            'requested_by_employee_id'  => auth()->user()?->employee_id ?? null,
            'approved_by_user_id'       => auth()->id(),
            'approved_at'               => now(),
        ]);

        return redirect()->back()->with('success', "Job Requisition #{$code} created successfully!");
    }

    // Toggle/Approve Requisition Status
    public function updateRequisitionStatus(Request $request, JobRequisition $requisition): RedirectResponse
    {
        $request->validate(['status' => 'required|in:draft,pending_approval,approved,published,closed,cancelled']);
        
        $requisition->update([
            'status' => $request->status,
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', "Requisition #{$requisition->requisition_code} status updated to " . strtoupper($request->status));
    }

    // Candidates Directory with Search, Sort & Filter
    public function candidates(Request $request): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $filters = [
            'search' => $request->get('search', ''),
            'status' => $request->get('status', ''),
            'source' => $request->get('source', ''),
            'sort'   => $request->get('sort', 'date_desc'),
        ];

        $query = Candidate::with(['applications.requisition', 'applications.interviews'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('candidate_code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        switch ($filters['sort']) {
            case 'name_asc':
                $query->orderBy('first_name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('first_name', 'desc');
                break;
            case 'exp_desc':
                $query->orderBy('total_experience_years', 'desc');
                break;
            case 'date_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $candidates = $query->paginate(12)->appends($filters);
        $requisitions = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', ['approved', 'published'])
            ->get();

        return view('modules.hrms.recruitment.candidates', compact('candidates', 'requisitions', 'filters'));
    }

    // Store Candidate Internally
    public function storeCandidate(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name'            => 'required|string|max:100',
            'last_name'             => 'nullable|string|max:100',
            'email'                 => 'required|email|max:255',
            'phone'                 => 'nullable|string|max:20',
            'current_location'      => 'nullable|string|max:100',
            'current_company'       => 'nullable|string|max:150',
            'current_designation'   => 'nullable|string|max:150',
            'total_experience_years'=> 'required|integer|min:0',
            'notice_period_days'    => 'nullable|integer|min:0',
            'source'                => 'required|in:direct,referral,linkedin,agency,other',
            'job_requisition_id'    => 'required|exists:job_requisitions,id',
            'resume'                => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $cndCount = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
        $code = 'CND-' . date('Y') . '-' . str_pad($cndCount, 4, '0', STR_PAD_LEFT);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('recruitment/resumes', 'public');
        }

        $candidate = Candidate::create([
            'tenant_id'             => $tenantId,
            'candidate_code'        => $code,
            'first_name'            => $request->first_name,
            'last_name'             => $request->last_name,
            'email'                 => $request->email,
            'phone'                 => $request->phone,
            'current_location'      => $request->current_location,
            'current_company'       => $request->current_company,
            'current_designation'   => $request->current_designation,
            'total_experience_years'=> $request->total_experience_years,
            'notice_period_days'    => $request->notice_period_days ?? 30,
            'resume_path'           => $resumePath,
            'source'                => $request->source,
            'status'                => 'active',
        ]);

        // Attach Candidate Application to Requisition
        CandidateApplication::create([
            'tenant_id'             => $tenantId,
            'candidate_id'          => $candidate->id,
            'job_requisition_id'    => $request->job_requisition_id,
            'current_stage'         => 'applied',
            'stage_updated_at'      => now(),
        ]);

        return redirect()->back()->with('success', "Candidate {$candidate->full_name} added successfully!");
    }

    // Pipeline Kanban View
    public function pipeline(int $requisitionId): View
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['department', 'designation'])
            ->findOrFail($requisitionId);

        $applications = CandidateApplication::where('job_requisition_id', $requisitionId)
            ->with(['candidate', 'interviews.scorecard', 'offer'])
            ->get();

        $employees = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();

        $stages = [
            'applied'     => 'Applied',
            'screening'   => 'Screening',
            'interview'   => 'Interviewing',
            'offer_sent'  => 'Offer Sent',
            'hired'       => 'Hired',
            'rejected'    => 'Rejected',
        ];

        return view('modules.hrms.recruitment.pipeline', compact('requisition', 'applications', 'stages', 'employees'));
    }

    // Update Application Stage
    public function updateStage(Request $request, CandidateApplication $application): RedirectResponse
    {
        $request->validate([
            'current_stage' => 'required|in:applied,screening,interview,interview_round_1,interview_round_2,interview_round_3,final_hr,offer_sent,hired,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        $application->update([
            'current_stage' => $request->current_stage,
            'rejection_reason' => $request->current_stage === 'rejected' ? $request->rejection_reason : null,
            'stage_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Candidate stage updated to " . strtoupper(str_replace('_', ' ', $request->current_stage)));
    }

    // Schedule Interview Round
    public function scheduleInterview(Request $request, CandidateApplication $application): RedirectResponse
    {
        $request->validate([
            'round_number'              => 'required|integer|min:1',
            'round_name'                => 'required|string|max:100',
            'scheduled_at'              => 'required|date',
            'interviewer_employee_id'   => 'nullable|exists:employees,id',
            'meeting_link'              => 'nullable|string|max:255',
            'venue_location'            => 'nullable|string|max:255',
            'round_notes'               => 'nullable|string',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        CandidateInterview::create([
            'tenant_id'               => $tenantId,
            'application_id'          => $application->id,
            'round_number'            => $request->round_number,
            'round_name'              => $request->round_name,
            'scheduled_at'            => $request->scheduled_at,
            'interviewer_employee_id' => $request->interviewer_employee_id,
            'meeting_link'            => $request->meeting_link,
            'venue_location'          => $request->venue_location,
            'status'                  => 'scheduled',
            'round_notes'             => $request->round_notes,
        ]);

        $stageMapping = [
            1 => 'interview_round_1',
            2 => 'interview_round_2',
            3 => 'interview_round_3',
        ];
        if (isset($stageMapping[$request->round_number])) {
            $application->update(['current_stage' => $stageMapping[$request->round_number], 'stage_updated_at' => now()]);
        }

        return redirect()->back()->with('success', "Interview Round #{$request->round_number} ({$request->round_name}) scheduled successfully!");
    }

    // Submit Scorecard
    public function submitScorecard(Request $request, CandidateInterview $interview): RedirectResponse
    {
        $request->validate([
            'technical_rating'      => 'required|integer|min:1|max:5',
            'communication_rating'  => 'required|integer|min:1|max:5',
            'culture_fit_rating'    => 'required|integer|min:1|max:5',
            'overall_rating'        => 'required|integer|min:1|max:5',
            'recommendation'        => 'required|in:pass,hold,reject',
            'feedback_notes'        => 'nullable|string',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        InterviewScorecard::updateOrCreate(
            ['interview_id' => $interview->id],
            [
                'tenant_id'             => $tenantId,
                'interviewer_user_id'   => auth()->id(),
                'technical_rating'      => $request->technical_rating,
                'communication_rating'  => $request->communication_rating,
                'culture_fit_rating'    => $request->culture_fit_rating,
                'overall_rating'        => $request->overall_rating,
                'recommendation'        => $request->recommendation,
                'feedback_notes'        => $request->feedback_notes,
            ]
        );

        $interview->update(['status' => 'completed']);

        if ($request->recommendation === 'reject') {
            $interview->application->update([
                'current_stage' => 'rejected',
                'rejection_reason' => 'Failed Interview Round: ' . $interview->round_name,
                'stage_updated_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', "Scorecard submitted for {$interview->round_name}!");
    }

    // Create Job Offer
    public function createOffer(Request $request, CandidateApplication $application): RedirectResponse
    {
        $request->validate([
            'offered_designation_id' => 'required|exists:designations,id',
            'offered_department_id'  => 'required|exists:departments,id',
            'offered_annual_ctc'     => 'required|numeric|min:0',
            'joining_date'           => 'required|date',
            'offer_letter_notes'     => 'nullable|string',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $offerCount = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
        $code = 'OFR-' . date('Y') . '-' . str_pad($offerCount, 3, '0', STR_PAD_LEFT);

        JobOffer::updateOrCreate(
            ['application_id' => $application->id],
            [
                'tenant_id'              => $tenantId,
                'offer_code'             => $code,
                'offered_designation_id' => $request->offered_designation_id,
                'offered_department_id'  => $request->offered_department_id,
                'offered_annual_ctc'     => $request->offered_annual_ctc,
                'joining_date'           => $request->joining_date,
                'offer_letter_notes'     => $request->offer_letter_notes,
                'status'                 => 'accepted',
                'accepted_at'            => now(),
            ]
        );

        $application->update([
            'current_stage' => 'offer_sent',
            'stage_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Job Offer #{$code} generated and accepted!");
    }

    // 1-Click Candidate to HRMS Employee Conversion
    public function convertToEmployee(JobOffer $offer): RedirectResponse
    {
        if ($offer->converted_employee_id) {
            return redirect()->back()->with('error', "Candidate has already been converted to an Employee!");
        }

        $application = $offer->application;
        $candidate = $application->candidate;

        DB::transaction(function () use ($offer, $application, $candidate) {
            $tenantId = function_exists('tenant_id') ? tenant_id() : null;
            $empCount = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
            $empCode = 'EMP-' . date('Y') . '-' . str_pad($empCount, 3, '0', STR_PAD_LEFT);

            // Inherit Company, Business Unit, Branch from Department
            $department = Department::find($offer->offered_department_id);

            $employee = Employee::create([
                'tenant_id'         => $tenantId,
                'employee_code'     => $empCode,
                'first_name'        => $candidate->first_name,
                'last_name'         => $candidate->last_name,
                'email'             => $candidate->email,
                'phone'             => $candidate->phone,
                'department_id'     => $offer->offered_department_id,
                'designation_id'    => $offer->offered_designation_id,
                'company_id'        => $department?->company_id,
                'business_unit_id'  => $department?->business_unit_id,
                'branch_id'         => $department?->branch_id,
                'joining_date'      => $offer->joining_date ?? now(),
                'employment_type'   => 'full_time',
                'status'            => 'active',
            ]);

            $offer->update(['converted_employee_id' => $employee->id]);
            $candidate->update(['status' => 'hired']);
            $application->update([
                'current_stage' => 'hired',
                'stage_updated_at' => now(),
            ]);

            $req = $application->requisition;
            if ($req && $req->vacancies > 0) {
                $req->decrement('vacancies');
                if ($req->vacancies === 0) {
                    $req->update(['status' => 'closed']);
                }
            }
        });

        return redirect()->route('hrms.employees.index')
            ->with('success', "🎉 Candidate {$candidate->full_name} converted to Employee #{$empCode} successfully!");
    }
}
