<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Domains\HRMS\Models\SopSection;
use App\Domains\HRMS\Models\SopVersionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SopRepository implements SopRepositoryInterface
{
    /**
     * Get aggregate dashboard data, listing collections, and compliance analytics.
     */
    public function getIndexData(array $filters, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array
    {
        // Auto-assign any published SOP that currently has 0 staff assignments
        $unassignedSops = SopDocument::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->has('assignments', '=', 0)
            ->get();
        foreach ($unassignedSops as $uSop) {
            $this->dispatchAssignments($uSop);
        }

        // 1. Stats Calculations
        $totalActive = SopDocument::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->count();

        $totalDraftOrReview = SopDocument::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->count();

        $totalAssignments = SopAssignment::where('tenant_id', $tenantId)->count();
        $totalAcknowledged = SopAssignment::where('tenant_id', $tenantId)->where('status', 'acknowledged')->count();
        $orgComplianceRate = $totalAssignments > 0 ? round(($totalAcknowledged / $totalAssignments) * 100, 1) : 0.0;

        $overdueCount = SopAssignment::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->count();

        // 2. Directory Query (SOP Documents)
        $docQuery = SopDocument::with(['category', 'department', 'creator', 'approver', 'sections'])
            ->withCount(['assignments', 'assignments as acknowledged_count' => function ($q) {
                $q->where('status', 'acknowledged');
            }])
            ->where('tenant_id', $tenantId);

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $docQuery->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('summary', 'like', $term)
                  ->orWhere('objective', 'like', $term)
                  ->orWhere('scope', 'like', $term);
            });
        }

        if (!empty($filters['category_id'])) {
            $docQuery->where('sop_category_id', $filters['category_id']);
        }

        if (!empty($filters['department_id'])) {
            $docQuery->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['status'])) {
            $docQuery->where('status', $filters['status']);
        }

        if (!empty($filters['criticality'])) {
            $docQuery->where('criticality', $filters['criticality']);
        }

        if (!empty($filters['sort'])) {
            match ($filters['sort']) {
                'oldest' => $docQuery->oldest(),
                'title_asc' => $docQuery->orderBy('title', 'asc'),
                'title_desc' => $docQuery->orderBy('title', 'desc'),
                'code_asc' => $docQuery->orderBy('code', 'asc'),
                'code_desc' => $docQuery->orderBy('code', 'desc'),
                default => $docQuery->latest(),
            };
        } else {
            $docQuery->latest();
        }

        $sops = $docQuery->paginate(15, ['*'], 'sop_page')->withQueryString();

        // 3. My SOPs (For the logged-in employee)
        $allMyAssignments = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        $myPendingAssignments = collect();
        $myCompletedAssignments = collect();
        $myTotalRequired = 0;
        $myCompletedCount = 0;
        $myPendingCount = 0;
        $myTotalCount = 0;

        if ($currentEmployee) {
            $baseMyAssignments = SopAssignment::whereHas('document', fn($q) => $q->where('status', 'published'))
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id);

            $myPendingCount = (clone $baseMyAssignments)->where('status', 'pending')->count();
            $myCompletedCount = (clone $baseMyAssignments)->where('status', 'acknowledged')->count();
            $myTotalCount = $myPendingCount + $myCompletedCount;
            $myTotalRequired = $myTotalCount;

            $myQuery = SopAssignment::with(['document.category', 'document.department', 'document.sections'])
                ->whereHas('document', fn($q) => $q->where('status', 'published'))
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $currentEmployee->id);

            // Filter search
            if (!empty($filters['my_search'])) {
                $term = '%' . trim($filters['my_search']) . '%';
                $myQuery->whereHas('document', function ($q) use ($term) {
                    $q->where('title', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('summary', 'like', $term)
                        ->orWhere('objective', 'like', $term)
                        ->orWhereHas('category', fn($cq) => $cq->where('name', 'like', $term));
                });
            }

            // Filter status
            if (!empty($filters['my_status'])) {
                if ($filters['my_status'] === 'overdue') {
                    $myQuery->where('status', 'pending')
                        ->whereNotNull('due_date')
                        ->where('due_date', '<', now()->toDateString());
                } else {
                    $myQuery->where('status', $filters['my_status']);
                }
            }

            // Filter category
            if (!empty($filters['my_category_id'])) {
                $myQuery->whereHas('document', fn($q) => $q->where('sop_category_id', $filters['my_category_id']));
            }

            // Sorting
            $mySort = $filters['my_sort'] ?? 'pending_first';
            match ($mySort) {
                'newest' => $myQuery->latest('assigned_at'),
                'oldest' => $myQuery->oldest('assigned_at'),
                'due_soon' => $myQuery->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC'),
                'title_asc' => $myQuery->join('sop_documents as sd_my', 'sop_assignments.sop_document_id', '=', 'sd_my.id')->orderBy('sd_my.title', 'asc')->select('sop_assignments.*'),
                'title_desc' => $myQuery->join('sop_documents as sd_my', 'sop_assignments.sop_document_id', '=', 'sd_my.id')->orderBy('sd_my.title', 'desc')->select('sop_assignments.*'),
                'code_asc' => $myQuery->join('sop_documents as sd_my', 'sop_assignments.sop_document_id', '=', 'sd_my.id')->orderBy('sd_my.code', 'asc')->select('sop_assignments.*'),
                default => $myQuery->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->latest('assigned_at'),
            };

            $allMyAssignments = $myQuery->paginate(10, ['*'], 'my_page')->withQueryString();
        }

        // 4. Compliance Matrix Data (SOP Compliance Breakdown)
        $complianceMatrix = SopDocument::with(['category', 'department'])
            ->withCount([
                'assignments as total_assigned',
                'assignments as acknowledged_count' => fn($q) => $q->where('status', 'acknowledged'),
                'assignments as pending_count' => fn($q) => $q->where('status', 'pending'),
                'assignments as overdue_count' => fn($q) => $q->where('status', 'pending')->whereNotNull('due_date')->where('due_date', '<', now()->toDateString()),
            ])
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->get();

        // 5. Masters for Dropdowns and Categories Tab
        $allCategories = SopCategory::where('tenant_id', $tenantId)->orderBy('name')->get();

        $catQuery = SopCategory::where('tenant_id', $tenantId)->withCount('documents');

        if (!empty($filters['cat_search'])) {
            $catTerm = '%' . trim($filters['cat_search']) . '%';
            $catQuery->where(function ($q) use ($catTerm) {
                $q->where('name', 'like', $catTerm)
                    ->orWhere('code', 'like', $catTerm)
                    ->orWhere('description', 'like', $catTerm);
            });
        }

        if (!empty($filters['cat_status'])) {
            $catQuery->where('status', $filters['cat_status']);
        }

        if (isset($filters['cat_has_sops']) && $filters['cat_has_sops'] !== '') {
            if ($filters['cat_has_sops'] === 'yes') {
                $catQuery->has('documents');
            } elseif ($filters['cat_has_sops'] === 'no') {
                $catQuery->doesntHave('documents');
            }
        }

        $catSort = $filters['cat_sort'] ?? 'name_asc';
        match ($catSort) {
            'name_desc' => $catQuery->orderBy('name', 'desc'),
            'code_asc' => $catQuery->orderBy('code', 'asc'),
            'code_desc' => $catQuery->orderBy('code', 'desc'),
            'sops_desc' => $catQuery->orderBy('documents_count', 'desc'),
            'newest' => $catQuery->latest(),
            'oldest' => $catQuery->oldest(),
            default => $catQuery->orderBy('name', 'asc'),
        };

        $categories = $catQuery->get();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $designations = Designation::where('tenant_id', $tenantId)->orderBy('name')->get();
        $employees = Employee::where('tenant_id', $tenantId)->where('status', true)->orderBy('full_name')->get();

        // 6. Overdue & Pending Assignments for Action Table
        $overdueAssignments = SopAssignment::with(['document', 'employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereHas('document', fn($q) => $q->where('status', 'published'))
            ->orderByRaw('CASE WHEN due_date < ? THEN 0 ELSE 1 END', [now()->toDateString()])
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'totalActive' => $totalActive,
            'totalDraftOrReview' => $totalDraftOrReview,
            'orgComplianceRate' => $orgComplianceRate,
            'overdueCount' => $overdueCount,
            'sops' => $sops,
            'allMyAssignments' => $allMyAssignments,
            'myPendingCount' => $myPendingCount,
            'myTotalCount' => $myTotalCount,
            'myPendingAssignments' => $myPendingAssignments,
            'myCompletedAssignments' => $myCompletedAssignments,
            'myTotalRequired' => $myTotalRequired,
            'myCompletedCount' => $myCompletedCount,
            'complianceMatrix' => $complianceMatrix,
            'categories' => $categories,
            'allCategories' => $allCategories,
            'departments' => $departments,
            'designations' => $designations,
            'employees' => $employees,
            'overdueAssignments' => $overdueAssignments,
            'isHrOrAdmin' => $isHrOrAdmin,
            'activeTab' => $filters['active_tab'] ?? ($isHrOrAdmin ? 'directory' : 'my_sops'),
        ];
    }

    /**
     * Find an SOP document by ID with relations.
     */
    public function findDocument(int $id, int $tenantId): SopDocument
    {
        return SopDocument::with(['category', 'department', 'creator', 'approver', 'sections', 'versionHistories.creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Create a new SOP document with sections and initial version.
     */
    public function createDocument(array $validated, ?User $user): SopDocument
    {
        return DB::transaction(function () use ($validated, $user) {
            $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
            $companyId = $user?->company_id;

            // Generate unique code if not provided
            if (empty($validated['code'])) {
                $count = SopDocument::where('tenant_id', $tenantId)->count() + 1;
                $validated['code'] = 'SOP-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $validated['tenant_id'] = $tenantId;
            $validated['company_id'] = $companyId;
            $validated['created_by'] = $user?->id;
            $validated['version'] = '1.0';

            $sectionsData = $validated['sections'] ?? [];
            unset($validated['sections']);

            $sop = SopDocument::create($validated);

            // Save sections
            $this->syncSections($sop, $sectionsData, $tenantId, $companyId);

            // Create initial version history
            SopVersionHistory::create([
                'tenant_id' => $tenantId,
                'sop_document_id' => $sop->id,
                'version' => '1.0',
                'change_type' => 'minor',
                'changes_summary' => 'Initial SOP draft created.',
                'created_by' => $user?->id,
                'snapshot_data' => $sop->toArray(),
            ]);

            return $sop;
        });
    }

    /**
     * Update an existing SOP document, sections, and bump version history.
     */
    public function updateDocument(SopDocument $sop, array $validated, ?User $user): SopDocument
    {
        return DB::transaction(function () use ($sop, $validated, $user) {
            $tenantId = $sop->tenant_id;
            $companyId = $sop->company_id;

            $oldVersion = $sop->version;
            $sectionsData = $validated['sections'] ?? null;
            $changeSummary = $validated['change_summary'] ?? 'Updated SOP contents and requirements.';
            unset($validated['sections'], $validated['change_summary']);

            // Increment minor version (e.g. 1.0 -> 1.1)
            $parts = explode('.', $sop->version);
            $major = (int) ($parts[0] ?? 1);
            $minor = (int) ($parts[1] ?? 0) + 1;
            $newVersion = "{$major}.{$minor}";
            $validated['version'] = $newVersion;

            $sop->update($validated);

            if ($sectionsData !== null) {
                $this->syncSections($sop, $sectionsData, $tenantId, $companyId);
            }

            // Record version history
            SopVersionHistory::create([
                'tenant_id' => $tenantId,
                'sop_document_id' => $sop->id,
                'version' => $newVersion,
                'change_type' => 'minor',
                'changes_summary' => $changeSummary,
                'created_by' => $user?->id,
                'snapshot_data' => $sop->fresh(['sections'])->toArray(),
            ]);

            return $sop;
        });
    }

    /**
     * Delete an SOP document.
     */
    public function deleteDocument(SopDocument $sop): bool
    {
        return (bool) $sop->delete();
    }

    /**
     * Store new SOP Category.
     */
    public function storeCategory(array $validated): SopCategory
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = auth()->user()?->company_id;
        $validated['color'] = $validated['color'] ?? '#3b82f6';

        return SopCategory::create($validated);
    }

    /**
     * Update an SOP Category.
     */
    public function updateCategory(SopCategory $category, array $validated): bool
    {
        return $category->update($validated);
    }

    /**
     * Delete an SOP Category.
     */
    public function deleteCategory(SopCategory $category): bool
    {
        return (bool) $category->delete();
    }

    /**
     * Employee digital sign-off and acknowledgment.
     */
    public function acknowledgeDocument(SopDocument $sop, Employee $employee, array $data): SopAssignment
    {
        $tenantId = $sop->tenant_id;

        $assignment = SopAssignment::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'sop_document_id' => $sop->id,
                'employee_id' => $employee->id,
            ],
            [
                'company_id' => $sop->company_id,
                'assigned_at' => now(),
                'status' => 'pending',
            ]
        );

        $assignment->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'signature_data' => $data['signature_data'] ?? ($data['signature'] ?? null),
            'checklist_responses' => $data['checklist_responses'] ?? null,
            'ip_address' => request()->ip() ?: '0.0.0.0',
            'user_agent' => request()->userAgent() ?: 'Web Client',
        ]);

        return $assignment;
    }

    /**
     * Dispatch assignments to target employees.
     */
    public function dispatchAssignments(SopDocument $sop): int
    {
        if ($sop->status !== 'published') {
            return 0;
        }

        $tenantId = $sop->tenant_id;
        $companyId = $sop->company_id;

        $empQuery = Employee::where('tenant_id', $tenantId)->where(function ($q) {
            $q->where('status', true)->orWhere('status', 1)->orWhereNull('status');
        });

        if ($sop->target_audience_type === 'department') {
            if (!empty($sop->target_department_ids)) {
                $empQuery->whereIn('department_id', (array) $sop->target_department_ids);
            } elseif (!empty($sop->department_id)) {
                $empQuery->where('department_id', $sop->department_id);
            }
        } elseif ($sop->target_audience_type === 'designation') {
            if (!empty($sop->target_designation_ids)) {
                $empQuery->whereIn('designation_id', (array) $sop->target_designation_ids);
            } elseif (!empty($sop->designation_id)) {
                $empQuery->where('designation_id', $sop->designation_id);
            }
        } elseif ($sop->target_audience_type === 'custom') {
            if (!empty($sop->target_employee_ids)) {
                $empQuery->whereIn('id', (array) $sop->target_employee_ids);
            }
        } elseif (!empty($sop->department_id) && $sop->target_audience_type !== 'all') {
            $empQuery->where('department_id', $sop->department_id);
        }

        $employees = $empQuery->get();
        $dispatched = 0;

        foreach ($employees as $employee) {
            $assignment = SopAssignment::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'sop_document_id' => $sop->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'company_id' => $companyId ?? $employee->company_id,
                    'assigned_at' => now(),
                    'due_date' => now()->addDays($sop->acknowledgment_days_limit ?? 7),
                    'status' => 'pending',
                    'is_mandatory' => (bool) ($sop->is_mandatory ?? true),
                    'version_assigned' => $sop->version ?? '1.0',
                ]
            );

            if ($assignment->wasRecentlyCreated) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Send bulk compliance reminders.
     */
    public function sendBulkReminders(SopDocument $sop, int $tenantId): int
    {
        $pending = SopAssignment::where('tenant_id', $tenantId)
            ->where('sop_document_id', $sop->id)
            ->where('status', 'pending')
            ->get();

        return $pending->count();
    }

    /**
     * Generate compliance matrix audit CSV.
     */
    public function generateAuditCsv(int $tenantId): string
    {
        $assignments = SopAssignment::with(['document.category', 'employee.department', 'employee.designation'])
            ->where('tenant_id', $tenantId)
            ->latest('assigned_at')
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            __('hrms.sop.th_code'),
            __('hrms.sop.th_title'),
            __('hrms.sop.th_category'),
            __('hrms.sop.th_version'),
            __('hrms.sop.csv_th_emp_code'),
            __('hrms.sop.csv_th_emp_name'),
            __('hrms.sop.csv_th_department'),
            __('hrms.sop.csv_th_designation'),
            __('hrms.sop.csv_th_assigned_date'),
            __('hrms.sop.csv_th_due_date'),
            __('hrms.common.status'),
            __('hrms.sop.csv_th_signoff_timestamp'),
            __('hrms.sop.th_compliance'),
        ]);

        foreach ($assignments as $a) {
            fputcsv($output, [
                $a->document?->code ?? __('hrms.common.na'),
                $a->document?->title ?? __('hrms.common.na'),
                $a->document?->category?->name ?? __('hrms.sop.uncategorized'),
                $a->document?->version ?? '1.0',
                $a->employee?->employee_id ?? __('hrms.common.na'),
                $a->employee?->full_name ?? ($a->employee?->first_name ?? __('hrms.common.na')),
                $a->employee?->department?->name ?? __('hrms.common.na'),
                $a->employee?->designation?->name ?? __('hrms.common.na'),
                $a->assigned_at ? Carbon::parse($a->assigned_at)->format('Y-m-d') : __('hrms.common.na'),
                $a->due_date ? Carbon::parse($a->due_date)->format('Y-m-d') : __('hrms.common.na'),
                strtoupper($a->status),
                $a->acknowledged_at ? Carbon::parse($a->acknowledged_at)->format('Y-m-d H:i') : __('hrms.common.na'),
                $a->compliance_score ? ($a->compliance_score . '%') : __('hrms.common.na'),
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content ?: '';
    }

    /**
     * Helper to synchronize sections for an SOP.
     */
    private function syncSections(SopDocument $sop, array $sectionsData, int $tenantId, ?int $companyId): void
    {
        $sop->sections()->delete();

        $order = 1;
        foreach ($sectionsData as $s) {
            if (empty($s['title']) && empty($s['content'])) {
                continue;
            }

            $checklist = [];
            if (!empty($s['checklist_items'])) {
                if (is_array($s['checklist_items'])) {
                    $checklist = $s['checklist_items'];
                } else {
                    $checklist = array_values(array_filter(array_map('trim', explode("\n", (string) $s['checklist_items']))));
                }
            }

            SopSection::create([
                'tenant_id' => $tenantId,
                'sop_document_id' => $sop->id,
                'step_number' => $order++,
                'title' => $s['title'] ?? 'Section ' . $order,
                'content' => $s['content'] ?? '',
                'has_checklist' => !empty($checklist),
                'checklist_items' => $checklist,
            ]);
        }
    }

    /**
     * Export SOP audit report for a specific SOP.
     */
    public function exportAudit(int $id, int $tenantId): mixed
    {
        $sop = SopDocument::with(['category', 'department', 'creator', 'approver'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $assignments = SopAssignment::with(['employee.department', 'employee.designation'])
            ->where('sop_document_id', $sop->id)
            ->get();

        $totalAssigned = $assignments->count();
        $acknowledgedCount = $assignments->where('status', 'acknowledged')->count();
        $complianceRate = $totalAssigned > 0 ? round(($acknowledgedCount / $totalAssigned) * 100, 1) : 0;

        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM for Microsoft Excel compatibility
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Official Audit Header Block
        fputcsv($handle, [__('hrms.sop.csv_report_title')]);
        fputcsv($handle, [__('hrms.sop.csv_doc_code'), $sop->code, __('hrms.sop.csv_version'), $sop->version, __('hrms.sop.csv_status'), strtoupper($sop->status)]);
        fputcsv($handle, [__('hrms.sop.csv_doc_title'), $sop->title]);
        fputcsv($handle, [__('hrms.sop.csv_category'), $sop->category->name ?? __('hrms.sop.cat_general'), __('hrms.sop.csv_department'), $sop->department->name ?? __('hrms.sop.organization_wide')]);
        fputcsv($handle, [__('hrms.sop.csv_effective_date'), $sop->effective_date ? $sop->effective_date->format('Y-m-d') : __('hrms.common.na'), __('hrms.sop.csv_review_interval'), __('hrms.sop.csv_months_suffix', ['count' => $sop->review_interval_months])]);
        fputcsv($handle, [__('hrms.sop.csv_total_assigned'), $totalAssigned, __('hrms.sop.csv_compliant_staff'), $acknowledgedCount, __('hrms.sop.csv_compliance_rate'), $complianceRate . '%']);
        fputcsv($handle, [__('hrms.sop.csv_export_date'), now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, []); // Blank separator line

        // Compliance Roster Column Headers
        fputcsv($handle, [
            __('hrms.sop.csv_th_emp_code'),
            __('hrms.sop.csv_th_emp_name'),
            __('hrms.sop.csv_th_work_email'),
            __('hrms.sop.csv_th_department'),
            __('hrms.sop.csv_th_designation'),
            __('hrms.sop.csv_th_sop_version'),
            __('hrms.sop.csv_th_compliance_status'),
            __('hrms.sop.csv_th_assigned_date'),
            __('hrms.sop.csv_th_due_date'),
            __('hrms.sop.csv_th_signoff_timestamp'),
            __('hrms.sop.csv_th_signoff_ip'),
        ]);

        foreach ($assignments as $a) {
            $empName = $a->employee?->full_name ?? ($a->employee?->first_name ?? __('hrms.common.na'));
            fputcsv($handle, [
                $a->employee?->employee_id ?? ('EMP-' . ($a->employee?->id ?? $a->employee_id)),
                $empName,
                $a->employee?->office_email ?? ($a->employee?->personal_email ?? __('hrms.common.na')),
                $a->employee?->department?->name ?? __('hrms.common.na'),
                $a->employee?->designation?->name ?? __('hrms.common.na'),
                $a->version_assigned,
                strtoupper($a->status),
                $a->assigned_at ? $a->assigned_at->format('Y-m-d H:i') : '',
                $a->due_date ? $a->due_date->format('Y-m-d') : '',
                $a->acknowledged_at ? $a->acknowledged_at->format('Y-m-d H:i:s') : __('hrms.sop.csv_pending_signoff'),
                $a->ip_address ?? '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'SOP_Compliance_Audit_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $sop->code) . '_' . date('Ymd_His') . '.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
