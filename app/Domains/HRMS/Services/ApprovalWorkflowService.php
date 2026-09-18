<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Unified service handling dynamic approval workflows, hierarchy resolution,
 * and strict conflict-of-interest / anti-self-action guardrails across HRMS modules.
 */
class ApprovalWorkflowService
{
    /**
     * Resolve the designated approver for an employee's request.
     * Automatically escalates to higher management/HR if the employee has no reporting manager
     * or is themselves an HR/Manager (preventing self-approval).
     */
    public function resolveApprover(Employee $requester, string $module = 'general'): ?Employee
    {
        // Level 1: Direct Reporting Manager (must not be the requester themselves)
        if ($requester->reporting_manager_id && (int) $requester->reporting_manager_id !== (int) $requester->id) {
            $manager = Employee::find($requester->reporting_manager_id);
            if ($manager) {
                return $manager;
            }
        }

        // Level 2: Escalation to HR Director / HR Manager / VP (excluding requester)
        $hrApprover = Employee::where('company_id', $requester->company_id)
            ->where('id', '!=', $requester->id)
            ->whereIn('role', ['hr_director', 'hr_manager', 'admin', 'director'])
            ->first();

        if ($hrApprover) {
            return $hrApprover;
        }

        // Level 3: Fallback to Company Admin / CEO (excluding requester)
        return Employee::where('company_id', $requester->company_id)
            ->where('id', '!=', $requester->id)
            ->first();
    }

    /**
     * Determine if an actor (User or Employee) is authorized to approve a request for a requester.
     * Enforces the strict NO SELF-APPROVAL rule.
     */
    public function canApprove(User|Employee $actor, Employee $requester): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        // Rule 1: Anti Self-Approval (STRICT: Requester cannot approve their own request)
        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $requester->id) {
            return false;
        }

        // Rule 2: Super Admin override (allowed to approve anyone except themselves)
        if ($actor instanceof User && $this->isSuperAdmin($actor)) {
            return true;
        }

        // Rule 3: Direct Line Manager
        if ($actorEmployeeId && $requester->reporting_manager_id && (int) $actorEmployeeId === (int) $requester->reporting_manager_id) {
            return true;
        }

        // Rule 4: Escalated Line Manager (Manager's Manager)
        if ($actorEmployeeId && $requester->reportingManager && $requester->reportingManager->reporting_manager_id) {
            if ((int) $actorEmployeeId === (int) $requester->reportingManager->reporting_manager_id) {
                return true;
            }
        }

        // Rule 5: Authorized HR or Admin Role (for employees other than themselves)
        $actorEmployee = $actor instanceof Employee ? $actor : $this->getEmployeeFromUser($actor);
        if ($actorEmployee && in_array(strtolower($actorEmployee->role ?? ''), ['hr', 'hr_manager', 'hr_director', 'admin', 'super_admin'])) {
            return true;
        }

        return false;
    }

    /**
     * Authorize an approval action or throw a ValidationException.
     *
     * @throws ValidationException
     */
    public function authorizeApproval(User|Employee $actor, Employee $requester): void
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        if ($actorEmployeeId && (int) $actorEmployeeId === (int) $requester->id) {
            throw ValidationException::withMessages([
                'approval' => 'Self-approval is prohibited. You cannot approve your own request.',
            ]);
        }

        if (! $this->canApprove($actor, $requester)) {
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

        // Anti Self-Management: Cannot resolve/manage ticket created by oneself
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
            return false; // HR cannot be the authority signer for their own official document
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
     * Employee Profile Module: Prevent HR staff from modifying their own official office records
     * (salary, designation, department, reporting manager).
     */
    public function canEditOfficeRecord(User|Employee $actor, Employee $targetEmployee): bool
    {
        $actorEmployeeId = $this->getEmployeeIdForActor($actor);

        // Anti Self-Modification: Cannot edit one's own official office record
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
     * Check if user is system Super Admin.
     */
    private function isSuperAdmin(User $user): bool
    {
        return strtolower($user->role ?? '') === 'super_admin' || $user->email === 'admin@warrgyizmorsch.com';
    }
}
