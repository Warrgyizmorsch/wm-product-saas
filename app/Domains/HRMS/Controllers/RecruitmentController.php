<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecruitmentController extends Controller
{
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
            ->take(3)
            ->get();

        return view('modules.hrms.recruitment.requisitions', compact('requisitions', 'departments', 'designations', 'employees', 'filters', 'upcomingInterviews'));
    }

    // Store Job Requisition
    public function storeRequisition(Request $request): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.create');

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
        $this->authorizeHrms('hrms.recruitment.update');

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
        $this->authorizeHrms('hrms.recruitment.view');

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
        $this->authorizeHrms('hrms.recruitment.create');

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
            $resumePath = $request->file('resume')->store('recruitment/resumes', 'local');
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
        $this->authorizeHrms('hrms.recruitment.view');

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['department', 'designation'])
            ->findOrFail($requisitionId);

        $applications = CandidateApplication::where('job_requisition_id', $requisitionId)
            ->with(['candidate', 'interviews.scorecard', 'offer'])
            ->get();

        $employees = Employee::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $departments = Department::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $designations = Designation::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->get();
        $templates = DocumentTemplate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')
            ->get();

        $stages = [
            'applied'     => 'Applied',
            'screening'   => 'Screening',
            'interview'   => 'Interviewing',
            'offer_sent'  => 'Offer Sent',
            'hired'       => 'Hired',
            'rejected'    => 'Rejected',
        ];

        return view('modules.hrms.recruitment.pipeline', compact('requisition', 'applications', 'stages', 'employees', 'departments', 'designations', 'templates'));
    }

    // Update Application Stage
    public function updateStage(Request $request, CandidateApplication $application): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $request->validate([
            'current_stage' => 'required|in:applied,screening,interview,interview_round_1,interview_round_2,interview_round_3,final_hr,offer_sent,hired,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        $oldStage = $application->current_stage;
        $newStage = $request->current_stage;

        $stageHierarchy = [
            'applied'           => 0,
            'screening'         => 1,
            'interview'         => 2,
            'interview_round_1' => 2,
            'interview_round_2' => 2,
            'interview_round_3' => 2,
            'final_hr'          => 2,
            'offer_sent'        => 3,
            'hired'             => 4,
        ];

        $oldStageKey = in_array($oldStage, ['interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']) ? 'interview' : $oldStage;
        $newStageKey = in_array($newStage, ['interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']) ? 'interview' : $newStage;

        $oldIndex = $stageHierarchy[$oldStageKey] ?? 0;
        $newIndex = $stageHierarchy[$newStageKey] ?? 0;

        // Disallow backtracking to an earlier pipeline stage (unless moving to/from 'rejected')
        if ($oldStage !== 'rejected' && $newStage !== 'rejected' && $newIndex < $oldIndex) {
            return redirect()->back()->with('error', 'Backtracking candidates to a previous recruitment stage is not allowed.');
        }

        $application->update([
            'current_stage' => $newStage,
            'rejection_reason' => $newStage === 'rejected' ? $request->rejection_reason : null,
            'stage_updated_at' => now(),
        ]);

        $req = $application->requisition;
        if ($req) {
            if ($oldStage !== 'hired' && $newStage === 'hired') {
                if ($req->vacancies > 0) {
                    $req->decrement('vacancies');
                    if ($req->fresh()->vacancies === 0) {
                        $req->update(['status' => 'closed']);
                    }
                }
            } elseif ($oldStage === 'hired' && $newStage !== 'hired') {
                $req->increment('vacancies');
                if ($req->status === 'closed') {
                    $req->update(['status' => 'approved']);
                }
            }
        }

        return redirect()->back()->with('success', "Candidate stage updated to " . strtoupper(str_replace('_', ' ', $newStage)));
    }

    // Schedule Interview Round
    public function scheduleInterview(Request $request, CandidateApplication $application): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

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
        $this->authorizeHrms('hrms.recruitment.update');

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
        $this->authorizeHrms('hrms.recruitment.update');

        $request->validate([
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

        $existingOffer = JobOffer::where('application_id', $application->id)->first();
        if ($existingOffer && $existingOffer->offer_code) {
            $code = $existingOffer->offer_code;
        } else {
            $i = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count() + 1;
            do {
                $code = 'OFR-' . date('Y') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $exists = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('offer_code', $code)->exists();
                $i++;
            } while ($exists);
        }

        $offerLetterContent = null;
        if ($request->filled('document_template_id')) {
            $template = DocumentTemplate::find($request->document_template_id);
            if ($template) {
                $candidate = $application->candidate;
                $department = Department::find($request->offered_department_id);
                $designation = Designation::find($request->offered_designation_id);
                $company = $department?->company ?? \App\Domains\HRMS\Models\Company::first();

                // Process HR Signature image (if provided via Canvas Draw or Image File Upload)
                $hrName = $request->input('hr_name', auth()->user()?->name ?? 'HR Manager');
                $hrDesignation = $request->input('hr_designation', 'HR Manager');
                $hrSigUrl = null;

                if ($request->hasFile('hr_signature_file') && $request->file('hr_signature_file')->isValid()) {
                    $file = $request->file('hr_signature_file');
                    $hrSigPath = $file->store("signatures/hr_tenant_{$tenantId}", 'public');
                    $hrSigUrl = asset('storage/' . $hrSigPath);
                } elseif ($request->filled('hr_signature_data')) {
                    $hrSigData = $request->input('hr_signature_data');
                    if (str_starts_with($hrSigData, 'data:image')) {
                        $image = str_replace(' ', '+', preg_replace('/^data:image\/\w+;base64,/', '', $hrSigData));
                        $imageName = 'hr_sig_' . time() . '_' . \Str::random(6) . '.png';
                        $hrSigPath = "signatures/hr_tenant_{$tenantId}/{$imageName}";
                        \Storage::disk('public')->put($hrSigPath, base64_decode($image));
                        $hrSigUrl = asset('storage/' . $hrSigPath);
                    } else {
                        $hrSigUrl = $hrSigData;
                    }
                }

                if ($hrSigUrl) {
                    $hrSigHtml = '<div style="display:inline-block; text-align:left; margin:10px 0;">' .
                                 '<img src="' . $hrSigUrl . '" style="max-height:60px; max-width:200px; object-fit:contain; display:block;" alt="HR Signature" />' .
                                 '<div style="font-weight:bold; font-size:13px; margin-top:4px;">' . e($hrName) . '</div>' .
                                 '<div style="font-size:11px; color:#64748b;">' . e($hrDesignation) . '</div>' .
                                 '</div>';
                } else {
                    $hrSigHtml = '<div style="display:inline-block; text-align:left; margin:10px 0;">' .
                                 '<div style="font-weight:bold; font-size:13px;">' . e($hrName) . '</div>' .
                                 '<div style="font-size:11px; color:#64748b;">' . e($hrDesignation) . '</div>' .
                                 '</div>';
                }

                $rawContent = trim(($template->header_content ?? '') . "\n" . ($template->body_content ?? '') . "\n" . ($template->footer_content ?? ''));
                if (empty($rawContent)) {
                    $rawContent = $template->body_content ?? '';
                }

                $replacements = [
                    'candidate_name'  => $candidate?->full_name ?? 'Candidate',
                    'employee_name'   => $candidate?->full_name ?? 'Candidate',
                    'candidate_email' => $candidate?->email ?? '',
                    'email'           => $candidate?->email ?? '',
                    'candidate_phone' => $candidate?->phone ?? '',
                    'phone'           => $candidate?->phone ?? '',
                    'employee_id'     => $candidate?->candidate_code ?? '',
                    'job_title'       => $designation?->name ?? '',
                    'designation'     => $designation?->name ?? '',
                    'department'      => $department?->name ?? '',
                    'annual_ctc'      => number_format((float)$request->offered_annual_ctc, 2),
                    'joining_date'    => date('d M, Y', strtotime($request->joining_date)),
                    'offer_code'      => $code,
                    'company_name'    => $company?->company_name ?? config('app.name'),
                    'current_date'    => date('d M, Y'),
                    'date'            => date('d M, Y'),
                    'offer_notes'     => $request->offer_letter_notes ?? '',
                    'hr_signature'   => $hrSigHtml,
                    'hr_name'        => $hrName,
                    'hr_designation' => $hrDesignation,
                ];

                foreach ($replacements as $key => $val) {
                    $rawContent = str_replace(
                        ['{' . $key . '}', '{{' . $key . '}}', '{' . strtoupper($key) . '}', '{{' . strtoupper($key) . '}}'],
                        $val,
                        $rawContent
                    );
                }
                // Handle brackets like [HR Signature] or [hr_signature]
                $rawContent = str_replace(['[HR Signature]', '[hr_signature]', '[HR SIGNATURE]'], $hrSigHtml, $rawContent);
                // Strip any remaining curly braces around values e.g. {Sahil} -> Sahil
                $rawContent = preg_replace('/\{([^{}\n]*)\}/', '$1', $rawContent);
                $offerLetterContent = $rawContent;
            }
        }

        JobOffer::updateOrCreate(
            ['application_id' => $application->id],
            [
                'tenant_id'              => $tenantId,
                'offer_code'             => $code,
                'offered_designation_id' => $request->offered_designation_id,
                'offered_department_id'  => $request->offered_department_id,
                'offered_annual_ctc'     => $request->offered_annual_ctc,
                'joining_date'           => $request->joining_date,
                'document_template_id'   => $request->document_template_id,
                'offer_letter_content'   => $offerLetterContent,
                'offer_letter_notes'     => $request->offer_letter_notes,
                'status'                 => 'accepted',
                'accepted_at'            => now(),
            ]
        );

        $application->update([
            'current_stage' => 'offer_sent',
            'stage_updated_at' => now(),
        ]);

        return redirect()->back()->with('success', "Job Offer #{$code} generated and sent successfully!");
    }

    // Send Job Offer Email to Candidate
    public function sendOfferEmail(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.update');

        $request->validate([
            'to_email'   => 'required|email|max:255',
            'subject'    => 'required|string|max:255',
            'account_id' => ['nullable', Rule::exists('email_configurations', 'id')->where('tenant_id', current_tenant_id())],
        ]);

        $application = $offer->application;
        $candidate = $application?->candidate;

        if (!$candidate) {
            return redirect()->back()->with('error', 'Candidate information not found.');
        }

        $rawContent = $offer->offer_letter_content;
        if (empty($rawContent)) {
            $rawContent = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #1e293b; text-align: center;'>JOB OFFER LETTER</h2>
                    <p>Dear <strong>{$candidate->full_name}</strong>,</p>
                    <p>We are pleased to offer you employment for the position of <strong>" . ($offer->designation->name ?? 'Role') . "</strong>.</p>
                    <p><strong>Offer Details:</strong></p>
                    <ul>
                        <li><strong>Offer Reference:</strong> {$offer->offer_code}</li>
                        <li><strong>Annual Compensation (CTC):</strong> $" . number_format($offer->offered_annual_ctc, 2) . "</li>
                        <li><strong>Target Joining Date:</strong> " . (optional($offer->joining_date)->format('d M, Y') ?? 'TBD') . "</li>
                    </ul>
                    <p>Please review your offer details and contact HR for confirmation.</p>
                    <br><br>
                    <p>Sincerely,<br><strong>Human Resources Team</strong></p>
                </div>
            ";
        }

        $cleanLetterContent = preg_replace('/\{([^{}\n]*)\}/', '$1', $rawContent);

        // Render & compile PDF attachment
        $pdfFileName = "Job_Offer_{$offer->offer_code}.pdf";
        $pdfHtml = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Job Offer - {$offer->offer_code}</title>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 30px; color: #1e293b; font-size: 13px; line-height: 1.6; }
                    .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 25px; }
                    .header h1 { margin: 0; color: #1e293b; font-size: 22px; font-weight: bold; }
                    .header p { margin: 5px 0 0 0; color: #64748b; font-size: 12px; }
                    .offer-body { margin-bottom: 30px; }
                    .footer { margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 15px; font-size: 11px; color: #94a3b8; text-align: center; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>OFFICIAL JOB OFFER LETTER</h1>
                    <p>Ref: {$offer->offer_code} | Date: " . date('d M, Y') . "</p>
                </div>
                <div class='offer-body'>
                    {$cleanLetterContent}
                </div>
                <div class='footer'>
                    This is an officially generated document. Confidential &copy; " . date('Y') . ".
                </div>
            </body>
            </html>
        ";

        $tempPath = storage_path("app/public/recruitment/offers/{$pdfFileName}");
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($pdfHtml)->setPaper('a4', 'portrait');
            file_put_contents($tempPath, $pdf->output());
        } catch (\Throwable $pe) {
            \Illuminate\Support\Facades\Log::error("Failed to generate offer PDF: " . $pe->getMessage());
        }

        // Cover email text
        $designationName = $offer->designation->name ?? 'Role';
        $joiningDateStr = optional($offer->joining_date)->format('d M, Y') ?? 'TBD';

        $coverEmailBody = "
            <div style='font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.6; padding: 15px; background-color: #ffffff; border-radius: 8px;'>
                <p>Dear <strong>{$candidate->full_name}</strong>,</p>
                <p>We are pleased to inform you that your official Job Offer for the position of <strong>{$designationName}</strong> has been generated.</p>
                <p>Please find attached your official <strong>Job Offer Letter PDF document</strong> (<code>{$pdfFileName}</code>) containing all employment details and terms.</p>
                <div style='background-color: #f8fafc; border-left: 4px solid #2563eb; padding: 14px 18px; margin: 20px 0; border-radius: 4px;'>
                    <strong style='color: #1e3a8a; font-size: 14px;'>Offer Summary:</strong>
                    <ul style='margin: 8px 0 0 0; padding-left: 20px; color: #334155;'>
                        <li><strong>Offer Reference:</strong> {$offer->offer_code}</li>
                        <li><strong>Position:</strong> {$designationName}</li>
                        <li><strong>Target Joining Date:</strong> {$joiningDateStr}</li>
                    </ul>
                </div>
                <p>Please review the attached PDF document and let us know if you have any questions or require further clarification.</p>
                <br>
                <p>Best regards,<br><strong>Human Resources Team</strong></p>
            </div>
        ";

        $attachments = [];
        if (file_exists($tempPath)) {
            $attachments[] = [
                'path' => $tempPath,
                'name' => $pdfFileName,
                'mime' => 'application/pdf',
            ];
        }

        try {
            /** @var \App\Services\EmailService $emailService */
            $emailService = app(\App\Services\EmailService::class);
            $emailService->sendEmail([
                'to'         => $request->to_email,
                'subject'    => $request->subject,
                'body_html'  => $coverEmailBody,
                'account_id' => $request->account_id,
            ], $attachments);

            $offer->update(['status' => 'sent', 'sent_at' => now()]);

            return redirect()->back()->with('success', "📧 Job Offer PDF document (#{$offer->offer_code}) sent successfully to {$request->to_email}!");
        } catch (\Throwable $e) {
            // Fallback to default Mail facade if EmailService DB config throws error
            try {
                \Illuminate\Support\Facades\Mail::send([], [], function ($msg) use ($request, $coverEmailBody, $tempPath, $pdfFileName) {
                    $msg->to($request->to_email)
                        ->subject($request->subject)
                        ->html($coverEmailBody);

                    if (file_exists($tempPath)) {
                        $msg->attach($tempPath, [
                            'as'   => $pdfFileName,
                            'mime' => 'application/pdf',
                        ]);
                    }
                });

                $offer->update(['status' => 'sent', 'sent_at' => now()]);

                return redirect()->back()->with('success', "📧 Job Offer PDF document (#{$offer->offer_code}) sent successfully to {$request->to_email}!");
            } catch (\Throwable $ex) {
                \Illuminate\Support\Facades\Log::error("Failed to send Job Offer Email: " . $ex->getMessage());
                return redirect()->back()->with('error', "Could not send offer email: " . $ex->getMessage());
            }
        }
    }

    // Convert Candidate to HRMS Employee: Create User & Redirect to Pre-filled Employee Form
    public function convertToEmployee(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->authorizeHrms('hrms.recruitment.manage');
        if ($offer->converted_employee_id) {
            return redirect()->back()->with('error', "Candidate has already been converted to an Employee!");
        }

        $application = $offer->application;
        $candidate = $application?->candidate;

        if (!$candidate) {
            return redirect()->back()->with('error', "Candidate information not found.");
        }

        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        $user = \App\Models\User::where('email', $candidate->email)->first();

        if (!$user) {
            $user = \App\Models\User::create([
                'tenant_id' => $tenantId,
                'name'      => $candidate->full_name,
                'email'     => $candidate->email,
                'password'  => \Illuminate\Support\Facades\Hash::make('12345678'),
                'role'      => 'employee',
                'is_active' => true,
            ]);
        }

        // 1. Immediately update Candidate status and Application stage to 'hired'
        $candidate->update(['status' => 'hired']);
        if ($application && $application->current_stage !== 'hired') {
            $application->update([
                'current_stage'    => 'hired',
                'stage_updated_at' => now(),
            ]);

            $req = $application->requisition;
            if ($req && $req->vacancies > 0) {
                $req->decrement('vacancies');
                if ($req->fresh()->vacancies === 0) {
                    $req->update(['status' => 'closed']);
                }
            }
        }

        // 2. Redirect to Employee Directory to complete Employee profile
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

        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($candidate->resume_path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->response($candidate->resume_path);
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($candidate->resume_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->response($candidate->resume_path);
        }

        abort(404, 'Resume file missing.');
    }
}
