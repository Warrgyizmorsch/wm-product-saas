<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\HrmsNotification;
use App\Models\User;

class HrmsNotificationService
{
    /**
     * Send notification to a single User.
     */
    public static function send(
        User|int $user,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null
    ): ?HrmsNotification {
        $userId = $user instanceof User ? $user->id : $user;
        if (!$userId) {
            return null;
        }

        $recipientUser = $user instanceof User ? $user : User::find($userId);
        if (!$recipientUser) {
            return null;
        }

        $employee = Employee::where('user_id', $userId)->first();

        return HrmsNotification::create([
            'tenant_id' => $recipientUser->tenant_id ?? tenant_id() ?? 1,
            'company_id' => $employee?->company_id,
            'business_unit_id' => $employee?->business_unit_id,
            'branch_id' => $employee?->branch_id,
            'user_id' => $userId,
            'employee_id' => $employee?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'icon_class' => $iconClass,
            'data' => $extraData,
        ]);
    }

    /**
     * Send notification to an Employee model instance.
     */
    public static function sendToEmployee(
        Employee|int|string|null $employee = null,
        string $title = '',
        string $message = '',
        ?string $actionUrl = null,
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null,
        Employee|int|string|null $employeeId = null
    ): ?HrmsNotification {
        $target = $employee ?? $employeeId;
        if (!$target) {
            return null;
        }

        $employeeModel = $target instanceof Employee ? $target : Employee::find($target);
        if (!$employeeModel) {
            return null;
        }

        if (!$employeeModel->user_id) {
            $user = User::where('email', $employeeModel->office_email)
                ->orWhere('email', $employeeModel->personal_email)
                ->first();
            if ($user) {
                $employeeModel->user_id = $user->id;
                $employeeModel->saveQuietly();
            }
        }

        if (!$employeeModel->user_id) {
            return null;
        }

        return HrmsNotification::create([
            'tenant_id' => $employeeModel->tenant_id ?? tenant_id() ?? 1,
            'company_id' => $employeeModel->company_id,
            'business_unit_id' => $employeeModel->business_unit_id,
            'branch_id' => $employeeModel->branch_id,
            'user_id' => $employeeModel->user_id,
            'employee_id' => $employeeModel->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'icon_class' => $iconClass,
            'data' => $extraData,
        ]);
    }

    /**
     * Send notification to HR Admins / Managers.
     */
    public static function sendToHrAdmins(
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $type = 'general',
        string $iconClass = 'feather-shield',
        ?array $extraData = null
    ): void {
        $admins = User::whereHas('roleModel', function ($q) {
            $q->where('name', 'like', '%HR%')
              ->orWhere('name', 'like', '%Admin%')
              ->orWhere('name', 'like', '%Super%');
        })->orWhere('role', 'admin')->orWhere('role', 'hr_admin')->get();

        if ($admins->isEmpty()) {
            $admins = User::limit(5)->get();
        }

        foreach ($admins as $admin) {
            self::send($admin, $title, $message, $actionUrl, $type, $iconClass, $extraData);
        }
    }

    /**
     * Send notification to all active employees.
     */
    public static function sendToAllEmployees(
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $type = 'broadcast',
        string $iconClass = 'feather-volume-2',
        ?array $extraData = null
    ): void {
        $employees = Employee::where('status', true)->whereNotNull('user_id')->get();
        foreach ($employees as $employee) {
            self::sendToEmployee($employee, $title, $message, $actionUrl, $type, $iconClass, $extraData);
        }
    }

    /**
     * Send notification to all employees in a specific Branch.
     */
    public static function sendToBranch(
        int $branchId,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $type = 'general',
        string $iconClass = 'feather-map-pin',
        ?array $extraData = null
    ): void {
        $employees = Employee::where('branch_id', $branchId)->where('status', true)->get();
        foreach ($employees as $employee) {
            self::sendToEmployee($employee, $title, $message, $actionUrl, $type, $iconClass, $extraData);
        }
    }
}
