<?php

namespace App\Services\Notification;

use App\Domains\HRMS\Models\Employee;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     */
    public static function send(
        User|int $user,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $module = 'system',
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null
    ): ?Notification {
        $userId = $user instanceof User ? $user->id : $user;
        if (!$userId) {
            return null;
        }

        $recipientUser = $user instanceof User ? $user : User::find($userId);
        if (!$recipientUser) {
            return null;
        }

        $employee = Employee::where('user_id', $userId)->first();

        try {
            return Notification::create([
                'tenant_id' => $recipientUser->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1) ?? 1,
                'company_id' => $employee?->company_id,
                'business_unit_id' => $employee?->business_unit_id,
                'branch_id' => $employee?->branch_id,
                'user_id' => $userId,
                'employee_id' => $employee?->id,
                'module' => strtolower($module),
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => self::resolveUrl($actionUrl),
                'icon_class' => $iconClass,
                'data' => $extraData,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Notification creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Safely resolve action URL whether passed as a route name, full URL, or relative path.
     */
    private static function resolveUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (!str_contains($url, '/') && \Illuminate\Support\Facades\Route::has($url)) {
            try {
                return route($url);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $url;
    }

    /**
     * Send notification to an Employee model instance or Employee ID.
     */
    public static function sendToEmployee(
        Employee|int|string|null $employee = null,
        string $title = '',
        string $message = '',
        ?string $actionUrl = null,
        string $module = 'hrms',
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null,
        Employee|int|string|null $employeeId = null
    ): ?Notification {
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

        return self::send(
            user: $employeeModel->user_id,
            title: $title,
            message: $message,
            actionUrl: $actionUrl,
            module: $module,
            type: $type,
            iconClass: $iconClass,
            extraData: $extraData
        );
    }

    /**
     * Send notification to multiple user IDs.
     *
     * @param int[] $userIds
     */
    public static function sendToUserIds(
        array $userIds,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $module = 'system',
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null
    ): array {
        $notifications = [];
        $uniqueUserIds = array_unique(array_filter($userIds));

        foreach ($uniqueUserIds as $userId) {
            $notification = self::send($userId, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData);
            if ($notification) {
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /**
     * Send notification to users matching specific roles or admin privileges.
     *
     * @param string[] $roles
     */
    public static function sendToRoles(
        array $roles,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $module = 'system',
        string $type = 'general',
        string $iconClass = 'feather-shield',
        ?array $extraData = null
    ): void {
        $query = User::query();

        $tenantId = $extraData['tenant_id'] ?? (function_exists('tenant_id') ? tenant_id() : null) ?? auth()->user()?->tenant_id;
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $query->where(function ($q) use ($roles) {
            foreach ($roles as $role) {
                $q->orWhere('role', 'like', "%{$role}%")
                  ->orWhereHas('primaryRole', function ($rq) use ($role) {
                      $rq->where('name', 'like', "%{$role}%");
                  })
                  ->orWhereHas('roles', function ($rq) use ($role) {
                      $rq->where('name', 'like', "%{$role}%");
                  });
            }
        });

        $users = $query->get();

        foreach ($users as $u) {
            self::send($u, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData);
        }
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
        self::sendToRoles(
            roles: ['HR', 'Admin', 'Super'],
            title: $title,
            message: $message,
            actionUrl: $actionUrl,
            module: 'hrms',
            type: $type,
            iconClass: $iconClass,
            extraData: $extraData
        );
    }

    /**
     * Send notification to all active employees.
     */
    public static function sendToAllEmployees(
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $module = 'hrms',
        string $type = 'broadcast',
        string $iconClass = 'feather-volume-2',
        ?array $extraData = null
    ): void {
        $tenantId = $extraData['tenant_id'] ?? (function_exists('tenant_id') ? tenant_id() : null) ?? auth()->user()?->tenant_id;
        $query = Employee::where('status', true)->whereNotNull('user_id');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $employees = $query->get();
        foreach ($employees as $employee) {
            self::sendToEmployee($employee, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData);
        }
    }
}
