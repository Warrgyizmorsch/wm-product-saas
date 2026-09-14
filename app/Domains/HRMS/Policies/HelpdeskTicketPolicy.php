<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\Access\AccessService;

class HelpdeskTicketPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HelpdeskTicket $ticket): bool
    {
        $employee = Employee::resolveForUser($user);
        if ($employee) {
            if ((int) $employee->id === (int) $ticket->employee_id) {
                return true;
            }
            if ($ticket->assigned_to && (int) $employee->id === (int) $ticket->assigned_to) {
                return true;
            }
        }

        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HelpdeskTicket $ticket): bool
    {
        $employee = Employee::resolveForUser($user);
        if ($employee && $ticket->assigned_to && (int) $employee->id === (int) $ticket->assigned_to) {
            return true;
        }

        return $this->canManage($user);
    }

    public function postInternalNote(User $user): bool
    {
        return $this->canManage($user);
    }

    public function canManage(User $user): bool
    {
        return $this->access->allows($user, 'hrms.helpdesk.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }
}
