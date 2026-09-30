<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Domains\HRMS\Models\SopSection;
use App\Domains\HRMS\Models\SopVersionHistory;
use App\Domains\HRMS\Repositories\SopRepositoryInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SopService
{
    public function __construct(
        private readonly HrmsScopeService $scopeService,
        private readonly SopRepositoryInterface $sopRepository
    ) {}

    /**
     * Get aggregate dashboard data, listing collections, and compliance analytics.
     */
    public function getIndexData(array $filters, ?Employee $currentEmployee, bool $isHrOrAdmin, int $tenantId): array
    {
        $data = $this->sopRepository->getIndexData($filters, $currentEmployee, $isHrOrAdmin, $tenantId);
        $data['currentEmployee'] = $currentEmployee;
        $data['filters'] = $filters;
        return $data;
    }

    /**
     * Create a new SOP Document with sections and automatic assignment rules.
     */
    public function createSop(array $data, int $tenantId, ?User $user): SopDocument
    {
        return DB::transaction(function () use ($data, $tenantId, $user) {
            // Generate Code if empty
            $code = !empty($data['code']) ? trim($data['code']) : $this->generateSopCode($tenantId, $data['sop_category_id'] ?? null);

            $sop = SopDocument::create([
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? ($user?->company_id ?? null),
                'sop_category_id' => $data['sop_category_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'code' => $code,
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'objective' => $data['objective'] ?? null,
                'scope' => $data['scope'] ?? null,
                'prerequisites' => $data['prerequisites'] ?? null,
                'version' => $data['version'] ?? '1.0',
                'status' => $data['status'] ?? 'draft',
                'criticality' => $data['criticality'] ?? 'medium',
                'target_audience_type' => $data['target_audience_type'] ?? 'all',
                'target_department_ids' => isset($data['target_department_ids']) ? (array) $data['target_department_ids'] : null,
                'target_designation_ids' => isset($data['target_designation_ids']) ? (array) $data['target_designation_ids'] : null,
                'target_employee_ids' => isset($data['target_employee_ids']) ? (array) $data['target_employee_ids'] : null,
                'is_mandatory' => isset($data['is_mandatory']) ? (bool) $data['is_mandatory'] : true,
                'auto_assign_new_hires' => isset($data['auto_assign_new_hires']) ? (bool) $data['auto_assign_new_hires'] : true,
                'acknowledgment_days_limit' => (int) ($data['acknowledgment_days_limit'] ?? 7),
                'effective_date' => !empty($data['effective_date']) ? Carbon::parse($data['effective_date']) : now()->toDateString(),
                'review_interval_months' => (int) ($data['review_interval_months'] ?? 12),
                'next_review_date' => !empty($data['effective_date']) ? Carbon::parse($data['effective_date'])->addMonths((int) ($data['review_interval_months'] ?? 12)) : now()->addYear(),
                'created_by' => $user?->id,
                'approved_by' => ($data['status'] ?? '') === 'published' ? $user?->id : null,
                'approved_at' => ($data['status'] ?? '') === 'published' ? now() : null,
                'attachment_path' => $data['attachment_path'] ?? null,
            ]);

            // Create Sections / Procedure Steps
            if (!empty($data['sections']) && is_array($data['sections'])) {
                foreach ($data['sections'] as $index => $secData) {
                    if (empty($secData['title']) && empty($secData['content'])) {
                        continue;
                    }

                    $checklistItems = null;
                    if (!empty($secData['checklist_items'])) {
                        if (is_string($secData['checklist_items'])) {
                            $lines = array_filter(array_map('trim', explode("\n", $secData['checklist_items'])));
                            $checklistItems = array_map(fn($t, $i) => ['id' => $i + 1, 'text' => $t], $lines, array_keys($lines));
                        } else {
                            $checklistItems = $secData['checklist_items'];
                        }
                    }

                    SopSection::create([
                        'tenant_id' => $tenantId,
                        'sop_document_id' => $sop->id,
                        'step_number' => $index + 1,
                        'title' => $secData['title'] ?? ('Step ' . ($index + 1)),
                        'content' => $secData['content'] ?? '',
                        'has_checklist' => !empty($checklistItems),
                        'checklist_items' => $checklistItems,
                        'guidelines' => $secData['guidelines'] ?? null,
                        'attachment_path' => $secData['attachment_path'] ?? null,
                    ]);
                }
            }

            // Log Initial Version History
            SopVersionHistory::create([
                'tenant_id' => $tenantId,
                'sop_document_id' => $sop->id,
                'version' => $sop->version,
                'change_type' => 'minor',
                'changes_summary' => 'Initial SOP draft created.',
                'created_by' => $user?->id,
                'snapshot_data' => $sop->toArray(),
            ]);

            // Dispatch assignments to target staff
            $this->dispatchAssignments($sop);

            return $sop;
        });
    }

    /**
     * Update an existing SOP Document, handle version incrementing and assignment re-triggers.
     */
    public function updateSop(SopDocument $sop, array $data, ?User $user): SopDocument
    {
        return DB::transaction(function () use ($sop, $data, $user) {
            $isMajorUpdate = ($data['version_bump'] ?? '') === 'major';
            $isMinorUpdate = ($data['version_bump'] ?? '') === 'minor';

            $newVersion = $sop->version;
            if ($isMajorUpdate) {
                $parts = explode('.', $sop->version);
                $major = (int) ($parts[0] ?? 1) + 1;
                $newVersion = "{$major}.0";
            } elseif ($isMinorUpdate) {
                $parts = explode('.', $sop->version);
                $major = (int) ($parts[0] ?? 1);
                $minor = (int) ($parts[1] ?? 0) + 1;
                $newVersion = "{$major}.{$minor}";
            }

            $effectiveDate = !empty($data['effective_date']) ? Carbon::parse($data['effective_date']) : $sop->effective_date;
            $reviewInterval = (int) ($data['review_interval_months'] ?? $sop->review_interval_months);
            $nextReviewDate = $effectiveDate ? Carbon::parse($effectiveDate)->addMonths($reviewInterval) : null;

            $status = $data['status'] ?? $sop->status;
            $approvedBy = $sop->approved_by;
            $approvedAt = $sop->approved_at;

            if ($status === 'published' && $sop->status !== 'published') {
                $approvedBy = $user?->id;
                $approvedAt = now();
            }

            $sop->update([
                'sop_category_id' => $data['sop_category_id'] ?? $sop->sop_category_id,
                'department_id' => $data['department_id'] ?? $sop->department_id,
                'code' => $data['code'] ?? $sop->code,
                'title' => $data['title'] ?? $sop->title,
                'summary' => $data['summary'] ?? $sop->summary,
                'objective' => $data['objective'] ?? $sop->objective,
                'scope' => $data['scope'] ?? $sop->scope,
                'prerequisites' => $data['prerequisites'] ?? $sop->prerequisites,
                'version' => $newVersion,
                'status' => $status,
                'criticality' => $data['criticality'] ?? $sop->criticality,
                'target_audience_type' => $data['target_audience_type'] ?? $sop->target_audience_type,
                'target_department_ids' => isset($data['target_department_ids']) ? (array) $data['target_department_ids'] : $sop->target_department_ids,
                'target_designation_ids' => isset($data['target_designation_ids']) ? (array) $data['target_designation_ids'] : $sop->target_designation_ids,
                'target_employee_ids' => isset($data['target_employee_ids']) ? (array) $data['target_employee_ids'] : $sop->target_employee_ids,
                'is_mandatory' => isset($data['is_mandatory']) ? (bool) $data['is_mandatory'] : $sop->is_mandatory,
                'auto_assign_new_hires' => isset($data['auto_assign_new_hires']) ? (bool) $data['auto_assign_new_hires'] : $sop->auto_assign_new_hires,
                'acknowledgment_days_limit' => (int) ($data['acknowledgment_days_limit'] ?? $sop->acknowledgment_days_limit),
                'effective_date' => $effectiveDate,
                'review_interval_months' => $reviewInterval,
                'next_review_date' => $nextReviewDate,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'attachment_path' => $data['attachment_path'] ?? $sop->attachment_path,
            ]);

            // Recreate Sections if provided
            if (isset($data['sections']) && is_array($data['sections'])) {
                $sop->sections()->delete();
                foreach ($data['sections'] as $index => $secData) {
                    if (empty($secData['title']) && empty($secData['content'])) {
                        continue;
                    }

                    $checklistItems = null;
                    if (!empty($secData['checklist_items'])) {
                        if (is_string($secData['checklist_items'])) {
                            $lines = array_filter(array_map('trim', explode("\n", $secData['checklist_items'])));
                            $checklistItems = array_map(fn($t, $i) => ['id' => $i + 1, 'text' => $t], $lines, array_keys($lines));
                        } else {
                            $checklistItems = $secData['checklist_items'];
                        }
                    }

                    SopSection::create([
                        'tenant_id' => $sop->tenant_id,
                        'sop_document_id' => $sop->id,
                        'step_number' => $index + 1,
                        'title' => $secData['title'] ?? ('Step ' . ($index + 1)),
                        'content' => $secData['content'] ?? '',
                        'has_checklist' => !empty($checklistItems),
                        'checklist_items' => $checklistItems,
                        'guidelines' => $secData['guidelines'] ?? null,
                        'attachment_path' => $secData['attachment_path'] ?? null,
                    ]);
                }
            }

            // Log Revision History
            SopVersionHistory::create([
                'tenant_id' => $sop->tenant_id,
                'sop_document_id' => $sop->id,
                'version' => $newVersion,
                'change_type' => $isMajorUpdate ? 'major' : 'minor',
                'changes_summary' => $data['changes_summary'] ?? ($isMajorUpdate ? 'Major revision update.' : 'SOP details updated.'),
                'created_by' => $user?->id,
                'snapshot_data' => $sop->fresh(['sections'])->toArray(),
            ]);

            // If major update or newly published, re-dispatch assignments
            if ($sop->status === 'published') {
                if ($isMajorUpdate) {
                    // Reset existing assignments to pending for the new major version
                    SopAssignment::where('sop_document_id', $sop->id)->update([
                        'version_assigned' => $newVersion,
                        'status' => 'pending',
                        'acknowledged_at' => null,
                        'signature_data' => null,
                        'checklist_responses' => null,
                        'assigned_at' => now(),
                        'due_date' => now()->addDays($sop->acknowledgment_days_limit ?? 7)->toDateString(),
                    ]);
                }
                $this->dispatchAssignments($sop);
            }

            return $sop;
        });
    }

    /**
     * Dispatch assignments to eligible employees based on audience rules.
     */
    public function dispatchAssignments(SopDocument $sop): int
    {
        $empQuery = Employee::where('tenant_id', $sop->tenant_id)
            ->where(function ($q) {
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

        $targetEmployees = $empQuery->get();
        $assignedCount = 0;
        $dueDate = now()->addDays($sop->acknowledgment_days_limit ?? 7)->toDateString();

        foreach ($targetEmployees as $emp) {
            $existing = SopAssignment::where('sop_document_id', $sop->id)
                ->where('employee_id', $emp->id)
                ->first();

            if (!$existing) {
                SopAssignment::create([
                    'tenant_id' => $sop->tenant_id,
                    'company_id' => $sop->company_id ?? $emp->company_id,
                    'sop_document_id' => $sop->id,
                    'employee_id' => $emp->id,
                    'version_assigned' => $sop->version,
                    'status' => 'pending',
                    'is_mandatory' => (bool) $sop->is_mandatory,
                    'assigned_at' => now(),
                    'due_date' => $dueDate,
                ]);
                $assignedCount++;
            }
        }

        return $assignedCount;
    }

    /**
     * Auto-assign all relevant SOPs to a newly onboarded employee.
     */
    public function autoAssignForNewEmployee(Employee $employee): void
    {
        $tenantId = $employee->tenant_id;
        $sops = SopDocument::where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->where('auto_assign_new_hires', true)
            ->get();

        foreach ($sops as $sop) {
            $isEligible = false;

            if ($sop->target_audience_type === 'all') {
                $isEligible = true;
            } elseif ($sop->target_audience_type === 'department' && in_array($employee->department_id, (array) $sop->target_department_ids)) {
                $isEligible = true;
            } elseif ($sop->target_audience_type === 'designation' && in_array($employee->designation_id, (array) $sop->target_designation_ids)) {
                $isEligible = true;
            }

            if ($isEligible) {
                SopAssignment::firstOrCreate(
                    [
                        'sop_document_id' => $sop->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'company_id' => $employee->company_id,
                        'version_assigned' => $sop->version,
                        'status' => 'pending',
                        'is_mandatory' => (bool) $sop->is_mandatory,
                        'assigned_at' => now(),
                        'due_date' => now()->addDays($sop->acknowledgment_days_limit ?? 7)->toDateString(),
                    ]
                );
            }
        }
    }

    /**
     * Employee sign-off & digital acknowledgment.
     */
    public function acknowledgeAssignment(SopAssignment $assignment, array $data, string $ipAddress, string $userAgent): SopAssignment
    {
        $assignment->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'signature_data' => $data['signature_data'] ?? null,
            'checklist_responses' => $data['checklist_responses'] ?? null,
        ]);

        return $assignment;
    }

    /**
     * Send nudge / reminder to an employee.
     */
    public function sendReminder(SopAssignment $assignment): bool
    {
        $assignment->update([
            'last_reminded_at' => now(),
        ]);

        $emp = $assignment->employee;
        $doc = $assignment->document;

        if ($emp && $doc) {
            \App\Services\Notification\NotificationService::sendToEmployee(
                employee: $emp,
                title: 'Action Required: SOP Sign-Off',
                message: "Please review and acknowledge Standard Operating Procedure: {$doc->title} ({$doc->code}).",
                actionUrl: route('hrms.sop.show', $doc->id),
                module: 'hrms',
                type: 'sop_reminder',
                iconClass: 'feather-book-open',
                extraData: [
                    'sop_document_id' => $doc->id,
                    'sop_assignment_id' => $assignment->id,
                    'tenant_id' => $assignment->tenant_id,
                ]
            );
        }

        return true;
    }

    /**
     * Generate compliance matrix audit CSV.
     */
    public function generateAuditCsv(int $tenantId): string
    {
        return $this->sopRepository->generateAuditCsv($tenantId);
    }

    /**
     * Generate unique standard SOP code.
     */
    private function generateSopCode(int $tenantId, ?int $categoryId): string
    {
        $prefix = 'SOP';
        if ($categoryId) {
            $cat = SopCategory::find($categoryId);
            if ($cat && !empty($cat->code)) {
                $prefix = 'SOP-' . strtoupper($cat->code);
            }
        }

        $count = SopDocument::where('tenant_id', $tenantId)->count() + 1;
        return sprintf('%s-%03d', $prefix, $count);
    }
}
