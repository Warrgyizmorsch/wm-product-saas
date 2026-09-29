<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use App\Domains\HRMS\Repositories\RecruitmentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecruitmentController extends Controller
{
    public function __construct(
        private readonly RecruitmentRepositoryInterface $recruitmentRepository
    ) {}

    private function authorizeHrms(string $permission): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $access = app(AccessService::class);
        $context = ['tenant_id' => $user->tenant_id];

        $allowed = $access->allows($user, $permission, $context)
            || $access->allows($user, 'hr.settings.manage', $context)
            || $access->allows($user, 'hrms.recruitment.manage', $context);

        abort_unless($allowed, 403, 'Unauthorized action in Recruitment module.');
    }

    // Main Recruitment Entry Point (Redirects to Job Requisitions)
    public function index(): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.view');

        return redirect()->route('hrms.recruitment.requisitions.index');
    }

    // Requisitions List with Standard Search, Sort & Filter
    public function requisitions(Request $request): View
    {
        $this->authorizeHrms('hrms.recruitment.view');
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $data = $this->recruitmentRepository->getRequisitionsData($request->all(), $tenantId);

        return view('modules.hrms.recruitment.requisitions', $data);
    }

    // Store Job Requisition
    public function storeRequisition(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.create');

        $validated = $request->validate([
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
        $employeeId = auth()->user()?->employee_id ?? null;
        $userId = auth()->id();

        $req = $this->recruitmentRepository->storeRequisition($validated, $tenantId, $employeeId, $userId);

        return redirect()->back()->with('success', "Job Requisition #{$req->requisition_code} created successfully!");
    }

    // Toggle/Approve Requisition Status
    public function updateRequisitionStatus(Request $request, JobRequisition $requisition): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $request->validate(['status' => 'required|in:draft,pending_approval,approved,published,closed,cancelled']);
        
        $this->recruitmentRepository->updateRequisitionStatus($requisition, $request->status, auth()->id());

        return redirect()->back()->with('success', "Requisition #{$requisition->requisition_code} status updated to " . strtoupper($request->status));
    }

    // Candidates Directory with Search, Sort & Filter
    public function candidates(Request $request): View
    {
        $this->authorizeHrms('hrms.recruitment.view');
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $data = $this->recruitmentRepository->getCandidatesData($request->all(), $tenantId);

        return view('modules.hrms.recruitment.candidates', $data);
    }

    // Store Candidate Internally
    public function storeCandidate(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.create');

        $validated = $request->validate([
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
        $candidate = $this->recruitmentRepository->storeCandidate($validated, $request, $tenantId);

        return redirect()->back()->with('success', "Candidate {$candidate->full_name} added successfully!");
    }

    // Pipeline Kanban View
    public function pipeline(int $requisitionId): View
    {
        $this->authorizeHrms('hrms.recruitment.view');
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $data = $this->recruitmentRepository->getPipelineData($requisitionId, $tenantId);

        return view('modules.hrms.recruitment.pipeline', $data);
    }

    // Update Application Stage
    public function updateStage(Request $request, CandidateApplication $application): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $request->validate([
            'current_stage' => 'required|in:applied,screening,interview,interview_round_1,interview_round_2,interview_round_3,final_hr,offer_sent,hired,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        $result = $this->recruitmentRepository->updateStage($application, $request->current_stage, $request->rejection_reason);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    // Schedule Interview Round
    public function scheduleInterview(Request $request, CandidateApplication $application): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $validated = $request->validate([
            'round_number'              => 'required|integer|min:1',
            'round_name'                => 'required|string|max:100',
            'scheduled_at'              => 'required|date',
            'interviewer_employee_id'   => 'nullable|exists:employees,id',
            'meeting_link'              => 'nullable|string|max:255',
            'venue_location'            => 'nullable|string|max:255',
            'round_notes'               => 'nullable|string',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $this->recruitmentRepository->scheduleInterview($application, $validated, $tenantId);

        return redirect()->back()->with('success', "Interview Round #{$request->round_number} ({$request->round_name}) scheduled successfully!");
    }

    // Submit Scorecard
    public function submitScorecard(Request $request, CandidateInterview $interview): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $validated = $request->validate([
            'technical_rating'      => 'required|integer|min:1|max:5',
            'communication_rating'  => 'required|integer|min:1|max:5',
            'culture_fit_rating'    => 'required|integer|min:1|max:5',
            'overall_rating'        => 'required|integer|min:1|max:5',
            'recommendation'        => 'required|in:pass,hold,reject',
            'feedback_notes'        => 'nullable|string',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $this->recruitmentRepository->submitScorecard($interview, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', "Scorecard submitted for {$interview->round_name}!");
    }

    // Create Job Offer
    public function createOffer(Request $request, CandidateApplication $application): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $validated = $request->validate([
            'offered_designation_id' => 'required|exists:designations,id',
            'offered_department_id'  => 'required|exists:departments,id',
            'offered_annual_ctc'     => 'required|numeric|min:0',
            'joining_date'           => 'required|date',
            'document_template_id'   => 'nullable|exists:document_templates,id',
            'offer_letter_notes'     => 'nullable|string',
            'hr_name'                => 'nullable|string|max:255',
            'hr_designation'         => 'nullable|string|max:255',
            'hr_signature_data'      => 'nullable|string',
            'hr_signature_file'      => 'nullable|file|mimes:png,jpg,jpeg,webp|max:5120',
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $offer = $this->recruitmentRepository->createOffer($application, $validated, $request, $tenantId);

        return redirect()->back()->with('success', "Job Offer #{$offer->offer_code} generated and sent successfully!");
    }

    // Send Job Offer Email to Candidate
    public function sendOfferEmail(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $validated = $request->validate([
            'to_email'   => 'required|email|max:255',
            'subject'    => 'required|string|max:255',
            'account_id' => ['nullable', Rule::exists('email_configurations', 'id')->where('tenant_id', current_tenant_id())],
        ]);

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $result = $this->recruitmentRepository->sendOfferEmail($offer, $validated, $tenantId);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    // Convert Candidate to HRMS Employee: Create User & Redirect to Pre-filled Employee Form
    public function convertToEmployee(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.manage');

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $result = $this->recruitmentRepository->convertToEmployee($offer, $tenantId);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        $user = $result['user'];
        $candidate = $result['candidate'];

        return redirect()->route('hrms.employees.index', array_filter([
            'convert_offer_id' => $offer->id,
            'user_id'          => $user->id,
            'account_id'       => $request->account_id,
        ]))->with('success', "🎉 User account created for {$candidate->full_name}! Candidate moved to Hired stage. Please complete the Employee Profile below.");
    }

    public function downloadResume(Candidate $candidate)
    {
        $this->authorizeHrms('hrms.recruitment.view');

        if (!$candidate->resume_path) {
            abort(404, 'Candidate resume not found.');
        }

        if (Storage::disk('local')->exists($candidate->resume_path)) {
            return Storage::disk('local')->response($candidate->resume_path);
        }

        if (Storage::disk('public')->exists($candidate->resume_path)) {
            return Storage::disk('public')->response($candidate->resume_path);
        }

        abort(404, 'Resume file missing.');
    }
}
