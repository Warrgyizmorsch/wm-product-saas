<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Domains\HRMS\Repositories\SopRepositoryInterface;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Domains\HRMS\Services\SopService;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SopApiController extends Controller
{
    public function __construct(
        private readonly SopService $sopService,
        private readonly SopRepositoryInterface $sopRepository,
        private readonly HrmsScopeService $scopeService
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
     * Resolve current employee, tenant context, and role permission.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.sop.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        return [$tenantId, $user, $currentEmployee, (bool) $isHrOrAdmin];
    }

    // =========================================================================
    // 1. DASHBOARD & SUMMARY METRICS API
    // =========================================================================

    /**
     * GET /api/hrms/sop/summary
     * Retrieve aggregated SOP metrics and compliance KPI state.
     */
    public function summary(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $totalActive = SopDocument::where('tenant_id', $tenantId)->where('status', 'published')->count();
            $totalDraft = SopDocument::where('tenant_id', $tenantId)->where('status', 'draft')->count();

            $totalAssignments = SopAssignment::where('tenant_id', $tenantId)
                ->whereHas('document', fn($q) => $q->where('status', 'published'))
                ->count();

            $ackAssignments = SopAssignment::where('tenant_id', $tenantId)
                ->where('status', 'acknowledged')
                ->whereHas('document', fn($q) => $q->where('status', 'published'))
                ->count();

            $orgComplianceRate = $totalAssignments > 0 ? round(($ackAssignments / $totalAssignments) * 100, 1) : 0.0;

            $overdueCount = SopAssignment::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->whereHas('document', fn($q) => $q->where('status', 'published'))
                ->count();

            $myPendingCount = 0;
            $myCompletedCount = 0;
            if ($currentEmployee) {
                $myPendingCount = SopAssignment::where('tenant_id', $tenantId)
                    ->where('employee_id', $currentEmployee->id)
                    ->where('status', 'pending')
                    ->whereHas('document', fn($q) => $q->where('status', 'published'))
                    ->count();

                $myCompletedCount = SopAssignment::where('tenant_id', $tenantId)
                    ->where('employee_id', $currentEmployee->id)
                    ->where('status', 'acknowledged')
                    ->whereHas('document', fn($q) => $q->where('status', 'published'))
                    ->count();
            }

            return $this->sendSuccess([
                'total_active_sops'   => $totalActive,
                'total_draft_sops'    => $totalDraft,
                'org_compliance_rate' => $orgComplianceRate,
                'total_assignments'   => $totalAssignments,
                'total_acknowledged'  => $ackAssignments,
                'overdue_count'       => $overdueCount,
                'my_pending_count'    => $myPendingCount,
                'my_completed_count'  => $myCompletedCount,
                'is_admin_or_hr'      => $isHrOrAdmin,
            ], 'SOP dashboard summary loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 2. SOP DOCUMENTS CRUD APIS
    // =========================================================================

    /**
     * GET /api/hrms/sop/documents
     * Paginated and filtered list of SOP documents.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $query = SopDocument::with(['category:id,name,code,color,icon', 'department:id,name'])
                ->where('tenant_id', $tenantId);

            // If not HR/Admin, only show published SOPs
            if (!$isHrOrAdmin) {
                $query->where('status', 'published');
            } elseif ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category_id')) {
                $query->where('sop_category_id', $request->category_id);
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('title', 'like', "%{$s}%")
                      ->orWhere('code', 'like', "%{$s}%")
                      ->orWhere('summary', 'like', "%{$s}%");
                });
            }

            // Sorting
            $sort = $request->get('sort', 'newest');
            match ($sort) {
                'oldest'     => $query->orderBy('created_at', 'asc'),
                'title_asc'  => $query->orderBy('title', 'asc'),
                'title_desc' => $query->orderBy('title', 'desc'),
                'code_asc'   => $query->orderBy('code', 'asc'),
                default      => $query->orderBy('created_at', 'desc'),
            };

            $perPage = min((int) ($request->get('per_page', 15)), 100);
            $paginated = $query->paginate($perPage);

            // Transform items concisely
            $items = $paginated->getCollection()->map(function ($doc) use ($currentEmployee, $isHrOrAdmin) {
                $totalAssigned = $doc->assignments()->count();
                $ackCount = $doc->assignments()->where('status', 'acknowledged')->count();
                $complianceRate = $totalAssigned > 0 ? round(($ackCount / $totalAssigned) * 100, 1) : 0.0;

                $myStatus = null;
                if ($currentEmployee) {
                    $myAssign = $doc->assignments()->where('employee_id', $currentEmployee->id)->first();
                    $myStatus = $myAssign?->status;
                }

                return [
                    'id'                        => $doc->id,
                    'code'                      => $doc->code,
                    'title'                     => $doc->title,
                    'summary'                   => $doc->summary,
                    'version'                   => $doc->version,
                    'status'                    => $doc->status,
                    'criticality'               => $doc->criticality,
                    'is_mandatory'              => $doc->is_mandatory,
                    'category'                  => $doc->category ? [
                        'id'    => $doc->category->id,
                        'name'  => $doc->category->name,
                        'code'  => $doc->category->code,
                        'color' => $doc->category->color,
                    ] : null,
                    'department'                => $doc->department ? [
                        'id'   => $doc->department->id,
                        'name' => $doc->department->name,
                    ] : null,
                    'effective_date'            => $doc->effective_date?->format('Y-m-d'),
                    'review_interval_months'    => $doc->review_interval_months,
                    'next_review_date'          => $doc->next_review_date?->format('Y-m-d'),
                    'total_assigned'            => $totalAssigned,
                    'acknowledged_count'        => $ackCount,
                    'compliance_rate'           => $complianceRate,
                    'my_acknowledgment_status'  => $myStatus,
                    'capabilities'              => [
                        'can_view'         => true,
                        'can_edit'         => $isHrOrAdmin,
                        'can_delete'       => $isHrOrAdmin,
                        'can_publish'      => $isHrOrAdmin,
                        'can_assign'       => $isHrOrAdmin,
                        'can_acknowledge'  => ($myStatus === 'pending'),
                    ],
                    'created_at'                => $doc->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            return $this->sendSuccess([
                'items'      => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ], 'SOP documents retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/sop/documents/{id}
     * Retrieve single SOP document with procedural steps, requirements, and compliance summary.
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $doc = SopDocument::with([
                'category:id,name,code,color,icon',
                'department:id,name',
                'creator:id,name',
                'approver:id,name',
                'sections',
                'versionHistories.creator:id,name',
            ])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

            // If not HR/Admin and not published, restrict access
            if (!$isHrOrAdmin && $doc->status !== 'published') {
                return $this->sendError('This SOP procedure is currently under preparation and not available.', 403);
            }

            $myAssignment = null;
            if ($currentEmployee) {
                $assign = SopAssignment::where('sop_document_id', $doc->id)
                    ->where('employee_id', $currentEmployee->id)
                    ->first();

                if ($assign) {
                    $myAssignment = [
                        'id'               => $assign->id,
                        'status'           => $assign->status,
                        'is_mandatory'     => $assign->is_mandatory,
                        'version_assigned' => $assign->version_assigned,
                        'assigned_at'      => $assign->assigned_at?->format('Y-m-d H:i:s'),
                        'due_date'         => $assign->due_date?->format('Y-m-d'),
                        'acknowledged_at'  => $assign->acknowledged_at?->format('Y-m-d H:i:s'),
                        'ip_address'       => $assign->ip_address,
                    ];
                }
            }

            $totalAssigned = $doc->assignments()->count();
            $ackCount = $doc->assignments()->where('status', 'acknowledged')->count();
            $complianceRate = $totalAssigned > 0 ? round(($ackCount / $totalAssigned) * 100, 1) : 0.0;

            // Formatted sections / steps
            $sections = $doc->sections->map(function ($sec) {
                return [
                    'id'              => $sec->id,
                    'step_number'     => $sec->step_number,
                    'title'           => $sec->title,
                    'content'         => $sec->content,
                    'has_checklist'   => (bool) $sec->has_checklist,
                    'checklist_items' => $sec->checklist_items,
                    'guidelines'      => $sec->guidelines,
                ];
            });

            // Version histories
            $versions = $doc->versionHistories->map(function ($vh) {
                return [
                    'id'              => $vh->id,
                    'version'         => $vh->version,
                    'change_type'     => $vh->change_type,
                    'changes_summary' => $vh->changes_summary,
                    'author'          => $vh->creator?->name ?? 'System',
                    'created_at'      => $vh->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            return $this->sendSuccess([
                'id'                        => $doc->id,
                'code'                      => $doc->code,
                'title'                     => $doc->title,
                'summary'                   => $doc->summary,
                'objective'                 => $doc->objective,
                'scope'                     => $doc->scope,
                'prerequisites'             => $doc->prerequisites,
                'version'                   => $doc->version,
                'status'                    => $doc->status,
                'criticality'               => $doc->criticality,
                'target_audience_type'      => $doc->target_audience_type,
                'is_mandatory'              => $doc->is_mandatory,
                'acknowledgment_days_limit' => $doc->acknowledgment_days_limit,
                'effective_date'            => $doc->effective_date?->format('Y-m-d'),
                'review_interval_months'    => $doc->review_interval_months,
                'next_review_date'          => $doc->next_review_date?->format('Y-m-d'),
                'author'                    => $doc->creator?->name ?? 'System Admin',
                'approver'                  => $doc->approver?->name ?? ($doc->status === 'published' ? ($doc->creator?->name ?? 'Admin') : null),
                'attachment_url'            => $doc->attachment_path ? asset('storage/' . $doc->attachment_path) : null,
                'category'                  => $doc->category ? [
                    'id'   => $doc->category->id,
                    'name' => $doc->category->name,
                    'code' => $doc->category->code,
                ] : null,
                'department'                => $doc->department ? [
                    'id'   => $doc->department->id,
                    'name' => $doc->department->name,
                ] : null,
                'compliance'                => [
                    'total_assigned'     => $totalAssigned,
                    'acknowledged_count' => $ackCount,
                    'compliance_rate'    => $complianceRate,
                ],
                'capabilities'              => [
                    'can_view'         => true,
                    'can_edit'         => $isHrOrAdmin,
                    'can_delete'       => $isHrOrAdmin,
                    'can_publish'      => $isHrOrAdmin,
                    'can_assign'       => $isHrOrAdmin,
                    'can_acknowledge'  => ($myAssignment && $myAssignment['status'] === 'pending'),
                ],
                'sections'                  => $sections,
                'version_histories'         => $versions,
                'my_assignment'             => $myAssignment,
            ], 'SOP document details loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/hrms/sop/documents
     * Store a new SOP document with procedural steps and automatic assignment rules.
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized. Only Admins and HR Managers can create SOP documents.', 403);
        }

        $validator = Validator::make($request->all(), [
            'title'                      => 'required|string|max:255',
            'code'                       => 'nullable|string|max:50',
            'sop_category_id'            => 'nullable|integer|exists:sop_categories,id',
            'department_id'              => 'nullable|integer|exists:departments,id',
            'criticality'                => 'nullable|in:low,medium,high,critical',
            'target_audience_type'       => 'nullable|in:all,department,designation,custom',
            'target_department_ids'      => 'nullable|array',
            'target_designation_ids'     => 'nullable|array',
            'target_employee_ids'        => 'nullable|array',
            'is_mandatory'               => 'nullable|boolean',
            'auto_assign_new_hires'      => 'nullable|boolean',
            'acknowledgment_days_limit'  => 'nullable|integer|min:1|max:365',
            'effective_date'             => 'nullable|date',
            'review_interval_months'     => 'nullable|integer|min:1|max:60',
            'summary'                    => 'nullable|string',
            'objective'                  => 'nullable|string',
            'scope'                      => 'nullable|string',
            'prerequisites'              => 'nullable|string',
            'status'                     => 'nullable|in:draft,published',
            'sections'                   => 'nullable|array',
            'sections.*.title'           => 'required_with:sections|string|max:255',
            'sections.*.content'         => 'required_with:sections|string',
            'sections.*.checklist_items' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $data = $validator->validated();

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('hrms/sops', 'public');
                $data['attachment_path'] = $path;
            }

            $sop = $this->sopService->createSop($data, $tenantId, $user);

            return $this->sendSuccess([
                'id'      => $sop->id,
                'code'    => $sop->code,
                'title'   => $sop->title,
                'version' => $sop->version,
                'status'  => $sop->status,
            ], 'Standard Operating Procedure created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/hrms/sop/documents/{id}
     * Update existing SOP document with version control options.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized. Only Admins and HR Managers can edit SOP documents.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'                      => 'sometimes|required|string|max:255',
            'code'                       => 'nullable|string|max:50',
            'sop_category_id'            => 'nullable|integer|exists:sop_categories,id',
            'department_id'              => 'nullable|integer|exists:departments,id',
            'criticality'                => 'nullable|in:low,medium,high,critical',
            'target_audience_type'       => 'nullable|in:all,department,designation,custom',
            'target_department_ids'      => 'nullable|array',
            'target_designation_ids'     => 'nullable|array',
            'target_employee_ids'        => 'nullable|array',
            'is_mandatory'               => 'nullable|boolean',
            'auto_assign_new_hires'      => 'nullable|boolean',
            'acknowledgment_days_limit'  => 'nullable|integer|min:1|max:365',
            'effective_date'             => 'nullable|date',
            'review_interval_months'     => 'nullable|integer|min:1|max:60',
            'summary'                    => 'nullable|string',
            'objective'                  => 'nullable|string',
            'scope'                      => 'nullable|string',
            'prerequisites'              => 'nullable|string',
            'status'                     => 'nullable|in:draft,published,archived',
            'version_bump'               => 'nullable|in:none,minor,major',
            'changes_summary'            => 'nullable|string|max:500',
            'sections'                   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $data = $validator->validated();

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('hrms/sops', 'public');
                $data['attachment_path'] = $path;
            }

            $updatedSop = $this->sopService->updateSop($sop, $data, $user);

            return $this->sendSuccess([
                'id'      => $updatedSop->id,
                'code'    => $updatedSop->code,
                'title'   => $updatedSop->title,
                'version' => $updatedSop->version,
                'status'  => $updatedSop->status,
            ], 'SOP document updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/hrms/sop/documents/{id}
     * Soft delete an SOP document.
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $sop->delete();
            return $this->sendSuccess(null, 'SOP document deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 3. LIFECYCLE ACTIONS (PUBLISH, ARCHIVE, ASSIGN, REMIND)
    // =========================================================================

    /**
     * POST /api/hrms/sop/documents/{id}/publish
     * Single-click publish and dispatch to all targeted employees.
     */
    public function publish(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $sop->update([
                'status'         => 'published',
                'approved_by'    => $user?->id,
                'approved_at'    => now(),
                'effective_date' => $sop->effective_date ?? now()->toDateString(),
            ]);

            $assignedCount = $this->sopService->dispatchAssignments($sop);

            return $this->sendSuccess([
                'id'             => $sop->id,
                'status'         => 'published',
                'assigned_count' => $assignedCount,
            ], "SOP published successfully. Dispatched to {$assignedCount} target employees.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/documents/{id}/archive
     * Archive an active SOP document.
     */
    public function archive(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $sop->update(['status' => 'archived']);
            return $this->sendSuccess(['id' => $sop->id, 'status' => 'archived'], 'SOP archived successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/documents/{id}/sync-assignments
     * Manually sync and dispatch assignments for new joiners or audience updates.
     */
    public function syncAssignments(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $assignedCount = $this->sopService->dispatchAssignments($sop);
            return $this->sendSuccess([
                'assigned_count' => $assignedCount,
            ], "Assignments synchronized successfully. Dispatched to {$assignedCount} employees.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/documents/{id}/bulk-remind
     * Send reminder notifications to all pending employees for an SOP.
     */
    public function bulkRemind(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $pendingAssignments = SopAssignment::with(['document', 'employee'])
                ->where('sop_document_id', $sop->id)
                ->where('status', 'pending')
                ->get();

            $count = 0;
            foreach ($pendingAssignments as $assignment) {
                $this->sopService->sendReminder($assignment);
                $count++;
            }

            return $this->sendSuccess(['reminders_sent' => $count], "Reminders sent to {$count} pending employees.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/sop/documents/{id}/export-audit
     * Export compliance audit report as CSV.
     */
    public function exportAudit(int $id): mixed
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized. Only HR/Admins can export audit logs.', 403);
        }

        return $this->sopRepository->exportAudit($id, $tenantId);
    }


    // =========================================================================
    // 4. EMPLOYEE SIGN-OFF & "MY SOPS" APIS
    // =========================================================================

    /**
     * GET /api/hrms/sop/my-sops
     * Retrieve the logged-in employee's pending and acknowledged procedures.
     */
    public function mySops(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$currentEmployee) {
            return $this->sendError('Employee profile not found for the authenticated user.', 404);
        }

        try {
            $statusFilter = $request->get('status', 'all'); // 'pending', 'acknowledged', 'all'

            $query = SopAssignment::with([
                'document.category:id,name,code,color',
                'document.department:id,name',
            ])
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $currentEmployee->id)
            ->whereHas('document', fn($q) => $q->where('status', 'published'));

            if ($statusFilter === 'pending') {
                $query->where('status', 'pending');
            } elseif ($statusFilter === 'acknowledged') {
                $query->where('status', 'acknowledged');
            }

            $assignments = $query->latest('assigned_at')->get();

            $items = $assignments->map(function ($a) {
                $isOverdue = $a->status === 'pending' && $a->due_date && $a->due_date->isPast();

                return [
                    'assignment_id'   => $a->id,
                    'sop_id'          => $a->document?->id,
                    'sop_code'        => $a->document?->code,
                    'sop_title'       => $a->document?->title,
                    'sop_version'     => $a->version_assigned,
                    'criticality'     => $a->document?->criticality,
                    'is_mandatory'    => $a->is_mandatory,
                    'category'        => $a->document?->category?->name ?? 'General',
                    'department'      => $a->document?->department?->name ?? 'Organization-Wide',
                    'status'          => $a->status,
                    'is_overdue'      => $isOverdue,
                    'assigned_at'     => $a->assigned_at?->format('Y-m-d H:i:s'),
                    'due_date'        => $a->due_date?->format('Y-m-d'),
                    'acknowledged_at' => $a->acknowledged_at?->format('Y-m-d H:i:s'),
                ];
            });

            return $this->sendSuccess([
                'pending_count'      => $assignments->where('status', 'pending')->count(),
                'acknowledged_count' => $assignments->where('status', 'acknowledged')->count(),
                'items'              => $items,
            ], 'My SOP assignments loaded successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/assignments/{id}/acknowledge
     * Employee digital sign-off and compliance acknowledgment.
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        $assignment = SopAssignment::where('tenant_id', $tenantId)->find($id);
        if (!$assignment) {
            return $this->sendError('Assignment record not found.', 404);
        }

        // Authorization check
        if ($currentEmployee && $assignment->employee_id !== $currentEmployee->id && !$isHrOrAdmin) {
            return $this->sendError('Unauthorized to acknowledge this SOP on behalf of another employee.', 403);
        }

        $validator = Validator::make($request->all(), [
            'confirm_understanding' => 'required',
            'signature_data'        => 'nullable|string',
            'checklist_responses'   => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('You must check the certification box to complete sign-off.', 422, $validator->errors());
        }

        try {
            $updated = $this->sopService->acknowledgeAssignment(
                $assignment,
                $request->all(),
                $request->ip() ?? '127.0.0.1',
                $request->userAgent() ?? 'Mobile / API Client'
            );

            return $this->sendSuccess([
                'assignment_id'   => $updated->id,
                'status'          => $updated->status,
                'acknowledged_at' => $updated->acknowledged_at?->format('Y-m-d H:i:s'),
                'ip_address'      => $updated->ip_address,
            ], 'Thank you! You have successfully acknowledged and digitally signed off on this SOP.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/assignments/{id}/remind
     * Send individual reminder notification to an employee.
     */
    public function remind(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $assignment = SopAssignment::with(['document', 'employee'])->where('tenant_id', $tenantId)->find($id);
        if (!$assignment) {
            return $this->sendError('Assignment record not found.', 404);
        }

        try {
            $this->sopService->sendReminder($assignment);
            $empName = $assignment->employee?->full_name ?? 'Employee';

            return $this->sendSuccess([
                'assignment_id'    => $assignment->id,
                'last_reminded_at' => $assignment->last_reminded_at?->format('Y-m-d H:i:s'),
            ], "Reminder sent to {$empName}.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 5. STAFF COMPLIANCE & OVERDUE ROSTER APIS (ADMIN / HR)
    // =========================================================================

    /**
     * GET /api/hrms/sop/documents/{id}/assignments
     * Get list of staff assigned to a specific SOP with status and sign-off timestamps.
     */
    public function documentAssignments(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $sop = SopDocument::where('tenant_id', $tenantId)->find($id);
        if (!$sop) {
            return $this->sendError('SOP document not found.', 404);
        }

        try {
            $assignments = SopAssignment::with(['employee.department', 'employee.designation'])
                ->where('sop_document_id', $sop->id)
                ->latest('assigned_at')
                ->get();

            $items = $assignments->map(function ($a) {
                return [
                    'assignment_id'   => $a->id,
                    'employee_id'     => $a->employee_id,
                    'employee_code'   => $a->employee?->employee_id ?? ('EMP-' . $a->employee_id),
                    'employee_name'   => $a->employee?->full_name ?? ($a->employee?->first_name ?? 'N/A'),
                    'department'      => $a->employee?->department?->name ?? 'N/A',
                    'designation'     => $a->employee?->designation?->name ?? 'N/A',
                    'version_assigned'=> $a->version_assigned,
                    'status'          => $a->status,
                    'assigned_at'     => $a->assigned_at?->format('Y-m-d H:i:s'),
                    'due_date'        => $a->due_date?->format('Y-m-d'),
                    'acknowledged_at' => $a->acknowledged_at?->format('Y-m-d H:i:s'),
                    'ip_address'      => $a->ip_address,
                ];
            });

            return $this->sendSuccess([
                'sop_code'           => $sop->code,
                'sop_title'          => $sop->title,
                'total_assigned'     => $assignments->count(),
                'acknowledged_count' => $assignments->where('status', 'acknowledged')->count(),
                'pending_count'      => $assignments->where('status', 'pending')->count(),
                'items'              => $items,
            ], 'Document assignments retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/hrms/sop/overdue-roster
     * Retrieve organization-wide overdue and pending staff assignments.
     */
    public function overdueRoster(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        try {
            $assignments = SopAssignment::with(['document', 'employee.department', 'employee.designation'])
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->whereHas('document', fn($q) => $q->where('status', 'published'))
                ->orderByRaw('CASE WHEN due_date < ? THEN 0 ELSE 1 END', [now()->toDateString()])
                ->orderBy('due_date', 'asc')
                ->get();

            $items = $assignments->map(function ($a) {
                $isOverdue = $a->due_date && $a->due_date->isPast();

                return [
                    'assignment_id' => $a->id,
                    'employee'      => [
                        'id'          => $a->employee?->id,
                        'code'        => $a->employee?->employee_id ?? ('EMP-' . $a->employee?->id),
                        'name'        => $a->employee?->full_name ?? ($a->employee?->first_name ?? 'N/A'),
                        'department'  => $a->employee?->department?->name ?? 'N/A',
                        'designation' => $a->employee?->designation?->name ?? 'N/A',
                    ],
                    'sop'           => [
                        'id'    => $a->document?->id,
                        'code'  => $a->document?->code,
                        'title' => $a->document?->title,
                    ],
                    'status'        => $a->status,
                    'is_overdue'    => $isOverdue,
                    'assigned_at'   => $a->assigned_at?->format('Y-m-d'),
                    'due_date'      => $a->due_date?->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess([
                'total_actionable' => $assignments->count(),
                'overdue_count'    => $items->where('is_overdue', true)->count(),
                'items'            => $items,
            ], 'Overdue roster retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // 6. CATEGORIES MASTER CRUD APIS
    // =========================================================================

    /**
     * GET /api/hrms/sop/categories
     * Retrieve all SOP categories with document count.
     */
    public function indexCategories(): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        try {
            $categories = SopCategory::withCount('documents')
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get()
                ->map(fn($c) => [
                    'id'              => $c->id,
                    'name'            => $c->name,
                    'code'            => $c->code,
                    'color'           => $c->color,
                    'icon'            => $c->icon,
                    'description'     => $c->description,
                    'status'          => $c->status,
                    'documents_count' => $c->documents_count,
                ]);

            return $this->sendSuccess($categories, 'SOP categories retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/hrms/sop/categories
     * Store new SOP Category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $data = $validator->validated();
            $data['tenant_id'] = $tenantId;
            $data['company_id'] = $user?->company_id;
            $data['color'] = $data['color'] ?? '#3b82f6';

            $category = SopCategory::create($data);

            return $this->sendSuccess([
                'id'   => $category->id,
                'name' => $category->name,
                'code' => $category->code,
            ], 'SOP Category created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/hrms/sop/categories/{id}
     * Update existing SOP category.
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $category = SopCategory::where('tenant_id', $tenantId)->find($id);
        if (!$category) {
            return $this->sendError('Category not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:100',
            'code'        => 'nullable|string|max:20',
            'color'       => 'nullable|string|max:20',
            'icon'        => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'status'      => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', 422, $validator->errors());
        }

        try {
            $category->update($validator->validated());

            return $this->sendSuccess([
                'id'   => $category->id,
                'name' => $category->name,
                'code' => $category->code,
            ], 'SOP Category updated successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/hrms/sop/categories/{id}
     * Delete SOP category.
     */
    public function destroyCategory(int $id): JsonResponse
    {
        [$tenantId, $user, $currentEmployee, $isHrOrAdmin] = $this->resolveContext();

        if (!$isHrOrAdmin) {
            return $this->sendError('Unauthorized.', 403);
        }

        $category = SopCategory::where('tenant_id', $tenantId)->find($id);
        if (!$category) {
            return $this->sendError('Category not found.', 404);
        }

        try {
            $category->delete();
            return $this->sendSuccess(null, 'SOP Category deleted successfully.');
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

}

