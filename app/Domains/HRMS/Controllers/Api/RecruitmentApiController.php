<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Candidate;
use App\Domains\HRMS\Models\CandidateApplication;
use App\Domains\HRMS\Models\CandidateInterview;
use App\Domains\HRMS\Models\InterviewScorecard;
use App\Domains\HRMS\Models\JobOffer;
use App\Domains\HRMS\Models\JobRequisition;
use App\Domains\HRMS\Repositories\RecruitmentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecruitmentApiController extends Controller
{
    public function __construct(
        private readonly RecruitmentRepositoryInterface $recruitmentRepository
    ) {}

    /**
     * Standardized JSON success response envelope.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Standardized JSON error response envelope.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Check authorization for Recruitment module.
     */
    private function authorizePermission(string $permission = 'hrms.recruitment.view'): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $access = app(AccessService::class);
        $context = ['tenant_id' => $user->tenant_id];

        return $access->allows($user, $permission, $context)
            || $access->allows($user, 'hr.settings.manage', $context)
            || $access->allows($user, 'hrms.recruitment.manage', $context);
    }

    private function getTenantId(): ?int
    {
        return tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
    }

    // =========================================================================
    // 1. SUMMARY / DASHBOARD HUB
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/summary
     * Recruitment KPI counters, open vacancies, candidate funnel, and upcoming interviews.
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized to view recruitment data.', 403);
        }

        $tenantId = $this->getTenantId();

        try {
            $totalRequisitions = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
            $openRequisitions = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereIn('status', ['approved', 'published'])->count();
            $totalVacancies = (int) JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereIn('status', ['approved', 'published'])->sum('vacancies');

            $totalCandidates = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
            $inInterviewCount = CandidateApplication::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereIn('current_stage', ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr'])->count();
            $offersCount = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count();
            $hiredCount = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('status', 'hired')->count();

            $upcomingInterviews = CandidateInterview::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'scheduled')
                ->with(['application.candidate', 'application.requisition:id,job_title,requisition_code', 'interviewer:id,full_name,employee_id'])
                ->orderBy('scheduled_at', 'asc')
                ->take(5)
                ->get();

            return $this->sendSuccess([
                'stats' => [
                    'total_requisitions' => $totalRequisitions,
                    'open_requisitions'  => $openRequisitions,
                    'total_vacancies'    => $totalVacancies,
                    'total_candidates'   => $totalCandidates,
                    'in_interview_count' => $inInterviewCount,
                    'offers_count'       => $offersCount,
                    'hired_count'        => $hiredCount,
                ],
                'upcoming_interviews' => $upcomingInterviews,
            ], 'Recruitment summary retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. JOB REQUISITIONS API
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/requisitions
     */
    public function indexRequisitions(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $query = JobRequisition::with(['department:id,name', 'designation:id,name', 'requestedBy:id,full_name,employee_id'])
            ->withCount('applications')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('job_title', 'like', $search)
                  ->orWhere('requisition_code', 'like', $search);
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $sort = $request->input('sort', 'date_desc');
        match ($sort) {
            'title_asc'      => $query->orderBy('job_title', 'asc'),
            'title_desc'     => $query->orderBy('job_title', 'desc'),
            'vacancies_desc' => $query->orderBy('vacancies', 'desc'),
            'date_asc'       => $query->orderBy('created_at', 'asc'),
            default          => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $requisitions = $query->paginate($perPage);

        $canUpdate = $this->authorizePermission('hrms.recruitment.update');
        $canDelete = $this->authorizePermission('hrms.recruitment.delete');
        $canCreate = $this->authorizePermission('hrms.recruitment.create');

        $requisitions->getCollection()->transform(function ($req) use ($canUpdate, $canDelete, $canCreate) {
            $req->capabilities = [
                'can_view'                 => true,
                'can_edit'                 => $canUpdate,
                'can_delete'               => $canDelete,
                'can_create_candidate'     => $canCreate,
                'can_schedule_interview'   => $canUpdate,
            ];
            return $req;
        });

        return $this->sendSuccess($requisitions, 'Job requisitions retrieved.');
    }

    /**
     * POST /api/hrms/recruitment/requisitions
     */
    public function storeRequisition(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.create')) {
            return $this->sendError('Unauthorized to create job requisitions.', 403);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $tenantId = $this->getTenantId();
            $employeeId = auth()->user()?->employee_id;
            $userId = auth()->id();

            $req = $this->recruitmentRepository->storeRequisition($validator->validated(), $tenantId, $employeeId, $userId);
            $req->load(['department:id,name', 'designation:id,name']);

            return $this->sendSuccess($req, "Job Requisition #{$req->requisition_code} created successfully.", 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/recruitment/requisitions/{id}
     */
    public function showRequisition(int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $requisition = JobRequisition::with(['department', 'designation', 'requestedBy', 'applications.candidate'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->find($id);

        if (!$requisition) {
            return $this->sendError('Job Requisition not found.', 404);
        }

        $requisition->capabilities = [
            'can_view'                 => true,
            'can_edit'                 => $this->authorizePermission('hrms.recruitment.update'),
            'can_delete'               => $this->authorizePermission('hrms.recruitment.delete'),
            'can_create_candidate'     => $this->authorizePermission('hrms.recruitment.create'),
            'can_schedule_interview'   => $this->authorizePermission('hrms.recruitment.update'),
        ];

        return $this->sendSuccess($requisition, 'Job Requisition details retrieved.');
    }

    /**
     * PUT /api/hrms/recruitment/requisitions/{id}
     */
    public function updateRequisition(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized to update requisitions.', 403);
        }

        $tenantId = $this->getTenantId();
        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$requisition) {
            return $this->sendError('Job Requisition not found.', 404);
        }

        $validator = Validator::make($request->all(), [
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
            'status'                => 'nullable|in:draft,pending_approval,approved,published,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $requisition->update($validator->validated());
            $requisition->load(['department:id,name', 'designation:id,name']);

            return $this->sendSuccess($requisition, "Job Requisition #{$requisition->requisition_code} updated successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * PATCH /api/hrms/recruitment/requisitions/{id}/status
     */
    public function updateRequisitionStatus(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,pending_approval,approved,published,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $tenantId = $this->getTenantId();
        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$requisition) {
            return $this->sendError('Job Requisition not found.', 404);
        }

        try {
            $this->recruitmentRepository->updateRequisitionStatus($requisition, $request->status, auth()->id());
            return $this->sendSuccess([
                'id'     => $requisition->id,
                'status' => $request->status,
            ], "Requisition status updated to " . strtoupper($request->status));
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/recruitment/requisitions/{id}
     */
    public function destroyRequisition(int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.manage')) {
            return $this->sendError('Unauthorized to delete job requisitions.', 403);
        }

        $tenantId = $this->getTenantId();
        $requisition = JobRequisition::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$requisition) {
            return $this->sendError('Job Requisition not found.', 404);
        }

        try {
            $requisition->delete();
            return $this->sendSuccess(null, 'Job Requisition deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 3. CANDIDATES DIRECTORY API
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/candidates
     */
    public function indexCandidates(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $query = Candidate::with(['applications.requisition:id,job_title,requisition_code', 'applications.interviews:id,application_id,round_name,status,scheduled_at'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('candidate_code', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('job_requisition_id')) {
            $query->whereHas('applications', fn($q) => $q->where('job_requisition_id', $request->job_requisition_id));
        }

        $sort = $request->input('sort', 'date_desc');
        match ($sort) {
            'name_asc' => $query->orderBy('first_name', 'asc'),
            'name_desc'=> $query->orderBy('first_name', 'desc'),
            'exp_desc' => $query->orderBy('total_experience_years', 'desc'),
            'date_asc' => $query->orderBy('created_at', 'asc'),
            default    => $query->orderBy('created_at', 'desc'),
        };

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $candidates = $query->paginate($perPage);

        $canUpdate = $this->authorizePermission('hrms.recruitment.update');
        $canDelete = $this->authorizePermission('hrms.recruitment.delete');

        $candidates->getCollection()->transform(function ($c) use ($canUpdate, $canDelete) {
            $c->capabilities = [
                'can_view'               => true,
                'can_edit'               => $canUpdate,
                'can_delete'             => $canDelete,
                'can_schedule_interview' => $canUpdate,
                'can_score'              => true,
                'can_create_offer'       => $canUpdate,
                'can_hire'               => $canUpdate,
            ];
            return $c;
        });

        return $this->sendSuccess($candidates, 'Candidates retrieved.');
    }

    /**
     * POST /api/hrms/recruitment/candidates
     */
    public function storeCandidate(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.create')) {
            return $this->sendError('Unauthorized to create candidate.', 403);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $tenantId = $this->getTenantId();
            $candidate = $this->recruitmentRepository->storeCandidate($validator->validated(), $request, $tenantId);
            $candidate->load(['applications.requisition:id,job_title,requisition_code']);

            return $this->sendSuccess($candidate, "Candidate {$candidate->full_name} registered successfully.", 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/hrms/recruitment/candidates/{id}
     */
    public function showCandidate(int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $candidate = Candidate::with([
            'applications.requisition.department',
            'applications.requisition.designation',
            'applications.interviews.scorecard',
            'applications.offer',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->find($id);

        if (!$candidate) {
            return $this->sendError('Candidate not found.', 404);
        }

        $canUpdate = $this->authorizePermission('hrms.recruitment.update');
        $canDelete = $this->authorizePermission('hrms.recruitment.delete');

        $candidate->capabilities = [
            'can_view'               => true,
            'can_edit'               => $canUpdate,
            'can_delete'             => $canDelete,
            'can_schedule_interview' => $canUpdate,
            'can_score'              => true,
            'can_create_offer'       => $canUpdate,
            'can_hire'               => $canUpdate,
        ];

        return $this->sendSuccess($candidate, 'Candidate profile retrieved.');
    }

    /**
     * PUT /api/hrms/recruitment/candidates/{id}
     */
    public function updateCandidate(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();
        $candidate = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$candidate) {
            return $this->sendError('Candidate not found.', 404);
        }

        $validator = Validator::make($request->all(), [
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
            'status'                => 'nullable|in:active,blacklisted,hired',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $candidate->update($validator->validated());
            return $this->sendSuccess($candidate, "Candidate profile updated successfully.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/hrms/recruitment/candidates/{id}
     */
    public function destroyCandidate(int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.manage')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();
        $candidate = Candidate::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$candidate) {
            return $this->sendError('Candidate not found.', 404);
        }

        try {
            $candidate->delete();
            return $this->sendSuccess(null, 'Candidate deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 4. PIPELINE / APPLICATIONS WORKFLOW API
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/pipeline/{requisitionId}
     */
    public function getPipeline(int $requisitionId): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        try {
            $data = $this->recruitmentRepository->getPipelineData($requisitionId, $tenantId);
            return $this->sendSuccess($data, 'Recruitment pipeline data retrieved.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    /**
     * PATCH /api/hrms/recruitment/applications/{id}/stage
     */
    public function updateApplicationStage(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized to update application stage.', 403);
        }

        $validator = Validator::make($request->all(), [
            'current_stage'    => 'required|in:applied,screening,interview,interview_round_1,interview_round_2,interview_round_3,final_hr,offer_sent,hired,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $tenantId = $this->getTenantId();
        $application = CandidateApplication::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$application) {
            return $this->sendError('Application not found.', 404);
        }

        try {
            $result = $this->recruitmentRepository->updateStage(
                $application,
                $request->current_stage,
                $request->rejection_reason
            );

            if (!$result['success']) {
                return $this->sendError($result['message'], 400);
            }

            return $this->sendSuccess([
                'application_id' => $application->id,
                'current_stage'  => $request->current_stage,
            ], $result['message']);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 5. INTERVIEWS & EVALUATOR SCORECARDS API
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/interviews
     */
    public function indexInterviews(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $query = CandidateInterview::with([
            'application.candidate',
            'application.requisition:id,job_title,requisition_code',
            'interviewer:id,full_name,employee_id',
            'scorecard',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('interviewer_employee_id')) {
            $query->where('interviewer_employee_id', $request->interviewer_employee_id);
        }

        if ($request->filled('application_id')) {
            $query->where('application_id', $request->application_id);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $interviews = $query->orderBy('scheduled_at', 'desc')->paginate($perPage);

        return $this->sendSuccess($interviews, 'Interviews retrieved.');
    }

    /**
     * POST /api/hrms/recruitment/applications/{id}/interviews
     * Schedule an Interview Round for an Application.
     */
    public function scheduleInterview(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized to schedule interviews.', 403);
        }

        $validator = Validator::make($request->all(), [
            'round_number'              => 'required|integer|min:1',
            'round_name'                => 'required|string|max:100',
            'scheduled_at'              => 'required|date',
            'interviewer_employee_id'   => 'nullable|exists:employees,id',
            'meeting_link'              => 'nullable|string|max:255',
            'venue_location'            => 'nullable|string|max:255',
            'round_notes'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $tenantId = $this->getTenantId();
        $application = CandidateApplication::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$application) {
            return $this->sendError('Application not found.', 404);
        }

        try {
            $interview = $this->recruitmentRepository->scheduleInterview($application, $validator->validated(), $tenantId);
            $interview->load(['interviewer:id,full_name,employee_id']);

            return $this->sendSuccess($interview, "Interview Round #{$request->round_number} ({$request->round_name}) scheduled successfully.", 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/recruitment/interviews/{id}/scorecard
     * Submit Interview Evaluation Scorecard.
     */
    public function submitScorecard(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'technical_rating'      => 'required|integer|min:1|max:5',
            'communication_rating'  => 'required|integer|min:1|max:5',
            'culture_fit_rating'    => 'required|integer|min:1|max:5',
            'overall_rating'        => 'required|integer|min:1|max:5',
            'recommendation'        => 'required|in:pass,hold,reject',
            'feedback_notes'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $tenantId = $this->getTenantId();
        $interview = CandidateInterview::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$interview) {
            return $this->sendError('Interview not found.', 404);
        }

        try {
            $scorecard = $this->recruitmentRepository->submitScorecard($interview, $validator->validated(), auth()->id(), $tenantId);
            return $this->sendSuccess($scorecard, "Scorecard submitted successfully for {$interview->round_name}.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    // =========================================================================
    // 6. JOB OFFERS & ONBOARDING CONVERSION API
    // =========================================================================

    /**
     * GET /api/hrms/recruitment/offers
     */
    public function indexOffers(Request $request): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.view')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();

        $query = JobOffer::with([
            'application.candidate',
            'application.requisition:id,job_title,requisition_code',
            'designation:id,name',
            'department:id,name',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $offers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->sendSuccess($offers, 'Job offers retrieved.');
    }

    /**
     * POST /api/hrms/recruitment/applications/{id}/offers
     * Generate / Create Job Offer for an Application.
     */
    public function createOffer(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized to create job offers.', 403);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        $tenantId = $this->getTenantId();
        $application = CandidateApplication::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$application) {
            return $this->sendError('Application not found.', 404);
        }

        try {
            $offer = $this->recruitmentRepository->createOffer($application, $validator->validated(), $request, $tenantId);
            $offer->load(['designation:id,name', 'department:id,name', 'application.candidate']);

            return $this->sendSuccess($offer, "Job Offer #{$offer->offer_code} generated successfully.", 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/recruitment/offers/{id}/send-email
     * Send official Offer Letter Email with PDF to Candidate.
     */
    public function sendOfferEmail(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.update')) {
            return $this->sendError('Unauthorized.', 403);
        }

        $tenantId = $this->getTenantId();
        $offer = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$offer) {
            return $this->sendError('Job Offer not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'to_email'   => 'required|email|max:255',
            'subject'    => 'required|string|max:255',
            'account_id' => ['nullable', Rule::exists('email_configurations', 'id')->where('tenant_id', $tenantId)],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed.', 422, $validator->errors());
        }

        try {
            $result = $this->recruitmentRepository->sendOfferEmail($offer, $validator->validated(), $tenantId);

            if (!$result['success']) {
                return $this->sendError($result['message'], 400);
            }

            return $this->sendSuccess(null, $result['message']);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/hrms/recruitment/offers/{id}/convert-to-employee
     * Convert Candidate to HRMS Employee / User Account.
     */
    public function convertToEmployee(Request $request, int $id): JsonResponse
    {
        if (!$this->authorizePermission('hrms.recruitment.manage')) {
            return $this->sendError('Unauthorized to convert candidate to employee.', 403);
        }

        $tenantId = $this->getTenantId();
        $offer = JobOffer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->find($id);

        if (!$offer) {
            return $this->sendError('Job Offer not found.', 404);
        }

        try {
            $result = $this->recruitmentRepository->convertToEmployee($offer, $tenantId);

            if (!$result['success']) {
                return $this->sendError($result['message'], 400);
            }

            $user = $result['user'];
            $candidate = $result['candidate'];

            return $this->sendSuccess([
                'user_id'          => $user->id,
                'user_email'       => $user->email,
                'user_name'        => $user->name,
                'candidate_id'     => $candidate->id,
                'convert_offer_id' => $offer->id,
            ], "User account created for {$candidate->full_name}. Candidate moved to Hired stage.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 400);
        }
    }
}
