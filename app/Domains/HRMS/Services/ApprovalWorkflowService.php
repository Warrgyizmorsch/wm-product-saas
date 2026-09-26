<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Unified enterprise approval engine handling dynamic workflows, multi-level hierarchy resolution,
 * admin master overrides, and strict conflict-of-interest / anti-self-action guardrails across HRMS modules.
 */
class ApprovalWorkflowService
{
    /**
     * Resolve the designated approver for an employee's request.
     * Automatically escalates to higher management/HR if the employee has no reporting manager
     * or is themselves an HR/Manager (preventing self-approval).
     */
    public function resolveApprover(Employee $requester, string $module = 'general', int $level = 1): ?Employee
    {
        // Level 1: Direct Reporting Manager (must not be the requester themselves)
        if ($level === 1) {
            if ($requester->reporting_manager_id && (int) $requester->reporting_manager_id !== (int) $requester->id) {
                $manager = Employee::where('company_id', $requester->company_id)
                    ->where('id', $requester->reporting_manager_id)
                    ->first();
                if ($manager) {
                    return $manager;
                }
            }
        }

        // Level 2 / Escalation: HR Manager / HR Director / Department Head
        $hrApprover = Employee::where('company_id', $requester->company_id)
            ->where('id', '!=', $requester->id)
            ->whereIn('role', ['hr_director', 'hr_manager', 'hr', 'admin', 'director'])
            ->first();

        if ($hrApprover) {
            return $hrApprover;
        }

        // Fallback: Skip-level manager (Manager's Manager)
        if ($requester->reportingManager && $requester->reportingManager->reporting_manager_id && (int) $requester->reportingManager->reporting_manager_id !== (int) $requester->id) {
            $skipManager = Employee::find($requester->reportingManager->reporting_manager_id);
            if ($skipManager) {
                return $skipManager;
            }
        }

        // Final Fallback: Company Admin / Executive (excluding requester)
        return Employee::where('company_id', $requester->company_id)
            ->where('id', '!=', $requester->id)
            ->first();
    }

    /**
     * Determine if an actor (User or Employee) is authorized to approve a request for a requester.
     * Enforces the self-approval rule (permitted if 'OWN' scope is explicitly granted to the role in matrix)
     * and standard enterprise escalation policies.
     */
    public function canApprove(User|Employee $actor, Employee $requester, mixed $requestModel = null, ?string $permission = null): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        // Self-Approval Evaluation:
        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $requester->id) {
            // Permitted if role explicitly has 'OWN' scope checked in Roles & Permissions matrix
            if ($this->allowsSelfApproval($actor, $requestModel, $permission)) {
                return true;
            }
            // Otherwise, block self-approval and enforce reporting line routing
            return false;
        }

        // Rule 2: Super Admin & Platform Admin Override
        if ($actor instanceof User && $this->isSuperAdmin($actor)) {
            return true;
        }

        // Rule 3: Company Admin & HR Admin Master Override
        // In standard enterprise platforms, administrators and HR managers have authority to intervene at any stage
        if ($this->isHrOrAdminActor($actor)) {
            return true;
        }

        // Multi-level workflow evaluation if request model context is provided
        if ($requestModel && isset($requestModel->current_level)) {
            $rules = $requestModel->leaveType->rules ?? ($requestModel->rules ?? []);
            $workflowLevel = $rules['approval']['workflow_level'] ?? '1_level';
            $firstApprover = $rules['approval']['first_approver'] ?? 'reporting_manager';
            $secondApprover = $rules['approval']['second_approver'] ?? 'hr_manager';
            $currentLevel = (string) ($requestModel->current_level ?? '1');

            if ($workflowLevel === '2_level') {
                if ($currentLevel === '2') {
                    // Level 2: Check designated second approver
                    if ($secondApprover === 'department_head') {
                        return $actorEmployeeId && $requester->department && (int) $actorEmployeeId === (int) $requester->department->head_employee_id;
                    } elseif ($secondApprover === 'reporting_manager') {
                        return $actorEmployeeId && (int) $actorEmployeeId === (int) $requester->reporting_manager_id;
                    }
                    // Standard Level 2 default is HR / Admin
                    return $this->isHrOrAdminActor($actor);
                } else {
                    // Level 1: Check designated first approver
                    if ($firstApprover === 'department_head') {
                        return $actorEmployeeId && $requester->department && (int) $actorEmployeeId === (int) $requester->department->head_employee_id;
                    }
                }
            }
        }

        // Rule 4: Direct Reporting Line Manager
        if ($actorEmployeeId && $requester->reporting_manager_id && (int) $actorEmployeeId === (int) $requester->reporting_manager_id) {
            return true;
        }

        // Rule 5: Skip-Level Line Manager (Manager's Manager)
        if ($actorEmployeeId && $requester->reportingManager && $requester->reportingManager->reporting_manager_id) {
            if ((int) $actorEmployeeId === (int) $requester->reportingManager->reporting_manager_id) {
                return true;
            }
        }

        // Rule 6: Department Head
        if ($actorEmployeeId && $requester->department && (int) $actorEmployeeId === (int) $requester->department->head_employee_id) {
            return true;
        }

        // Rule 7: Branch Manager
        if ($actorEmployeeId && $requester->branch && (int) $actorEmployeeId === (int) $requester->branch->manager_employee_id) {
            return true;
        }

        // Rule 8: Business Unit Head
        if ($actorEmployeeId && $requester->businessUnit && (int) $actorEmployeeId === (int) $requester->businessUnit->head_employee_id) {
            return true;
        }

        return false;
    }

    /**
     * Check if actor has explicit 'OWN' scope approval permission granted for this request domain.
     */
    public function allowsSelfApproval(User|Employee $actor, mixed $requestModel = null, ?string $permission = null): bool
    {
        $user = $actor instanceof User ? $actor : ($actor->user ?? null);
        if (!$user && $actor instanceof Employee) {
            $user = $this->getUserFromEmployee($actor);
        }

        if (!$user) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $permissionName = $permission ?? $this->resolveApprovalPermissionName($requestModel);
        if (!$permissionName) {
            return false;
        }

        return app(\App\Services\Access\AccessService::class)->hasExplicitScope(
            $user,
            $permissionName,
            \App\Models\Access\RolePermission::SCOPE_OWN,
            $user->tenant_id
        );
    }

    /**
     * Resolve corresponding HRMS approval permission name based on the request model.
     */
    public function resolveApprovalPermissionName(mixed $requestModel): ?string
    {
        if (is_string($requestModel)) {
            return str_starts_with($requestModel, 'hrms.') ? $requestModel : 'hrms.' . $requestModel;
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\LeaveRequest) {
            return 'hrms.leave_requests.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\WfhRequest) {
            return 'hrms.leave_requests.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\ShiftChangeRequest) {
            return 'hrms.shift_changes.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\OvertimeRequest) {
            return 'hrms.overtime.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\LeaveEncashment) {
            return 'hrms.leave_encashments.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\EmployeeExit) {
            return 'hrms.employee_exits.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\AttendanceCorrection) {
            return 'hrms.attendance.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\TravelRequest
            || $requestModel instanceof \App\Domains\HRMS\Models\CashAdvance
            || $requestModel instanceof \App\Domains\HRMS\Models\ExpenseReport) {
            return 'hrms.travel_expenses.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\Asset) {
            return 'hrms.assets.approve';
        }

        if ($requestModel instanceof \App\Domains\HRMS\Models\PayrollRun) {
            return 'hrms.payroll_runs.approve';
        }

        return 'hrms.leave_requests.approve';
    }

    /**
     * Check if actor possesses HR or Administrator authority.
     */
    public function isHrOrAdminActor(User|Employee $actor): bool
    {
        if ($actor instanceof User) {
            if ($this->isSuperAdmin($actor)) {
                return true;
            }
            $userRole = strtolower($actor->role ?? '');
            if (in_array($userRole, ['super_admin', 'super-admin', 'admin', 'company_admin', 'hr', 'hr_manager', 'hr_director'])) {
                return true;
            }
            if (method_exists($actor, 'hasHrPermission') && (
                $actor->hasHrPermission('hrms.leave_requests.approve') ||
                $actor->hasHrPermission('hr.settings.manage') ||
                $actor->hasHrPermission('hrms.wfh.approve')
            )) {
                return true;
            }
        }

        $employee = $actor instanceof Employee ? $actor : $this->getEmployeeFromUser($actor);
        if ($employee && in_array(strtolower($employee->role ?? ''), ['hr', 'hr_manager', 'hr_director', 'admin', 'super_admin', 'director'])) {
            return true;
        }

        return false;
    }

    /**
     * Authorize an approval action or throw a ValidationException.
     *
     * @throws ValidationException
     */
    public function authorizeApproval(User|Employee $actor, Employee $requester, mixed $requestModel = null, ?string $permission = null): void
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $requester->id) {
            if (! $this->allowsSelfApproval($actor, $requestModel, $permission)) {
                throw ValidationException::withMessages([
                    'approval' => 'Self-approval is prohibited for your role. This request must be approved by your manager.',
                ]);
            }
        }

        if (! $this->canApprove($actor, $requester, $requestModel, $permission)) {
            throw ValidationException::withMessages([
                'approval' => 'You are not authorized to approve this request.',
            ]);
        }
    }

    /**
     * Helpdesk Module: Prevent HR/Employee from managing/resolving their own tickets.
     */
    public function canManageTicket(User|Employee $actor, HelpdeskTicket $ticket): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $ticket->employee_id) {
            return false;
        }

        return true;
    }

    /**
     * Authorize ticket status update / management.
     *
     * @throws ValidationException
     */
    public function authorizeTicketManagement(User|Employee $actor, HelpdeskTicket $ticket): void
    {
        if (! $this->canManageTicket($actor, $ticket)) {
            throw ValidationException::withMessages([
                'ticket' => 'Conflict of Interest: You cannot manage or resolve helpdesk tickets raised by yourself.',
            ]);
        }
    }

    /**
     * Document Module: Prevent HR staff from acting as the approving authority for their own issued documents.
     */
    public function canSignDocumentAuthority(User|Employee $actor, Employee $recipient): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $recipient->id) {
            return false;
        }

        return true;
    }

    /**
     * Authorize HR authority signature on a document.
     *
     * @throws ValidationException
     */
    public function authorizeDocumentAuthoritySignature(User|Employee $actor, Employee $recipient): void
    {
        if (! $this->canSignDocumentAuthority($actor, $recipient)) {
            throw ValidationException::withMessages([
                'document' => 'Conflict of Interest: You cannot act as the approving HR authority on your own document.',
            ]);
        }
    }

    /**
     * Employee Profile Module: Prevent HR staff from modifying their own official office records.
     */
    public function canEditOfficeRecord(User|Employee $actor, Employee $targetEmployee): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $targetEmployee->id) {
            return false;
        }

        return true;
    }

    /**
     * Authorize official office record editing.
     *
     * @throws ValidationException
     */
    public function authorizeOfficeRecordEdit(User|Employee $actor, Employee $targetEmployee): void
    {
        if (! $this->canEditOfficeRecord($actor, $targetEmployee)) {
            throw ValidationException::withMessages([
                'employee' => 'Conflict of Interest: You cannot modify your own official office records (salary, designation, department).',
            ]);
        }
    }

    /**
     * Document Module: Determine if an actor is authorized to delete a document.
     */
    public function canDeleteDocument(User|Employee $actor, \App\Domains\HRMS\Models\Document $document): bool
    {
        if ($actor instanceof User && $this->isSuperAdmin($actor)) {
            return true;
        }

        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $document->documentable_id) {
            return false;
        }

        return true;
    }

    /**
     * Helper to get the Employee ID associated with a User or Employee actor.
     */
    public function getEmployeeIdForActor(User|Employee $actor): ?int
    {
        if ($actor instanceof Employee) {
            return $actor->id;
        }

        $employee = $this->getEmployeeFromUser($actor);
        return $employee ? $employee->id : null;
    }

    /**
     * Find the Employee model corresponding to a User account.
     */
    public function getEmployeeFromUser(User $user): ?Employee
    {
        return Employee::where('user_id', $user->id)
            ->orWhere(function ($q) use ($user) {
                if (! empty($user->email)) {
                    $q->where('personal_email', $user->email)
                      ->orWhere('office_email', $user->email);
                }
            })
            ->first();
    }

    /**
     * Find the User model corresponding to an Employee record.
     */
    public function getUserFromEmployee(Employee $employee): ?User
    {
        if ($employee->user) {
            return $employee->user;
        }

        if ($employee->user_id) {
            return User::find($employee->user_id);
        }

        return User::where('email', $employee->office_email)
            ->orWhere('email', $employee->personal_email)
            ->first();
    }

    /**
     * Check if user is system Super Admin.
     */
    private function isSuperAdmin(User $user): bool
    {
        return strtolower($user->role ?? '') === 'super_admin' 
            || strtolower($user->role ?? '') === 'super-admin'
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin());
    }
}
