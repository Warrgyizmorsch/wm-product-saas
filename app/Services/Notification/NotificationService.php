<?php

namespace App\Services\Notification;

use App\Domains\HRMS\Models\Employee;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Postpone execution of callback until after HTTP response is flushed to client.
     */
    public static function runDeferred(callable $callback): void
    {
        try {
            if (function_exists('defer')) {
                \defer($callback);
                return;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        try {
            if (function_exists('app') && method_exists(app(), 'terminating')) {
                app()->terminating($callback);
                return;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        try {
            $callback();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Deferred notification callback failed: " . $e->getMessage());
        }
    }

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
        ?array $extraData = null,
        bool $dispatchBroadcast = true
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
        if (!$employee && !empty($recipientUser->email)) {
            $employee = Employee::where(function ($q) use ($recipientUser) {
                $q->where('office_email', $recipientUser->email)
                  ->orWhere('personal_email', $recipientUser->email);
            })->first();
            if ($employee && !$employee->user_id) {
                $employee->user_id = $userId;
                $employee->saveQuietly();
            }
        }

        try {
            $notification = Notification::create([
                'tenant_id' => $recipientUser->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1) ?? 1,
                'company_id' => $employee?->company_id ?? $recipientUser->company_id,
                'business_unit_id' => $employee?->business_unit_id,
                'branch_id' => $employee?->branch_id ?? $recipientUser->branch_id,
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

            // Defer external cloud network broadcasts (FCM & Pusher) so the user's HTTP request completes instantly
            if ($dispatchBroadcast) {
                self::runDeferred(function () use ($recipientUser, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData, $notification, $userId) {
                    // Safely dispatch FCM Push Notification
                    if (config('firebase.auto_push_notifications', true)) {
                        try {
                            \App\Services\Firebase\FcmService::sendToUser(
                                user: $recipientUser,
                                title: $title,
                                body: $message,
                                actionUrl: self::resolveUrl($actionUrl),
                                extraData: array_merge($extraData ?? [], [
                                    'notification_id' => (string) ($notification->id ?? ''),
                                    'module' => (string) $module,
                                    'type' => (string) $type,
                                ])
                            );
                        } catch (\Throwable $fcmEx) {
                            \Illuminate\Support\Facades\Log::info("FCM push notification dispatch skipped: " . $fcmEx->getMessage());
                        }
                    }

                    // Real-time Pusher WebSockets Broadcast
                    try {
                        \App\Services\Pusher\PusherBroadcastService::broadcast(
                            channels: ["user-{$userId}"],
                            eventName: 'notification',
                            data: [
                                'id' => $notification->id,
                                'title' => $title,
                                'message' => $message,
                                'action_url' => self::resolveUrl($actionUrl),
                                'module' => (string) $module,
                                'type' => (string) $type,
                                'icon_class' => $iconClass,
                            ]
                        );
                    } catch (\Throwable $pusherEx) {
                        \Illuminate\Support\Facades\Log::info("Pusher broadcast dispatch skipped: " . $pusherEx->getMessage());
                    }
                });
            }

            return $notification;
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
     * Send notification to a specific user (supports User object, integer user_id, or named parameter userId).
     */
    public static function sendToUser(
        User|int|null $user = null,
        string $title = '',
        string $message = '',
        ?string $actionUrl = null,
        string $module = 'hrms',
        string $type = 'general',
        string $iconClass = 'feather-bell',
        ?array $extraData = null,
        User|int|null $userId = null
    ): ?Notification {
        $target = $user ?? $userId;
        if (!$target) {
            return null;
        }

        return self::send(
            user: $target,
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
        if (!$employeeModel && is_string($target)) {
            $employeeModel = Employee::where('employee_id', $target)->first();
        }
        if (!$employeeModel) {
            return null;
        }

        if (!$employeeModel->user_id) {
            $emails = array_filter([$employeeModel->office_email, $employeeModel->personal_email]);
            if (!empty($emails)) {
                $user = User::whereIn('email', $emails)->first();
                if ($user) {
                    $employeeModel->user_id = $user->id;
                    $employeeModel->saveQuietly();
                }
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
     * Batches Pusher broadcasting and defers external network calls.
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
        $uniqueUserIds = array_values(array_unique(array_filter($userIds)));

        if (empty($uniqueUserIds)) {
            return [];
        }

        // Fast path for single recipient
        if (count($uniqueUserIds) === 1) {
            $notif = self::send($uniqueUserIds[0], $title, $message, $actionUrl, $module, $type, $iconClass, $extraData, true);
            return $notif ? [$notif] : [];
        }

        // Multi-recipient: DB records created immediately without individual blocking network calls
        $validRecipientUsers = [];
        foreach ($uniqueUserIds as $userId) {
            $notification = self::send($userId, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData, false);
            if ($notification) {
                $notifications[] = $notification;
                $validRecipientUsers[] = $userId;
            }
        }

        if (empty($validRecipientUsers)) {
            return $notifications;
        }

        // Single batched Pusher broadcast + deferred FCM
        self::runDeferred(function () use ($validRecipientUsers, $title, $message, $actionUrl, $module, $type, $iconClass, $extraData, $notifications) {
            // 1. Single Pusher request targeting all recipient channels simultaneously
            try {
                $channels = array_map(fn($uid) => "user-{$uid}", $validRecipientUsers);
                $channelChunks = array_chunk($channels, 100);
                $firstNotifId = $notifications[0]->id ?? null;

                foreach ($channelChunks as $chunk) {
                    \App\Services\Pusher\PusherBroadcastService::broadcast(
                        channels: $chunk,
                        eventName: 'notification',
                        data: [
                            'id' => $firstNotifId,
                            'title' => $title,
                            'message' => $message,
                            'action_url' => self::resolveUrl($actionUrl),
                            'module' => (string) $module,
                            'type' => (string) $type,
                            'icon_class' => $iconClass,
                        ]
                    );
                }
            } catch (\Throwable $pusherEx) {
                \Illuminate\Support\Facades\Log::info("Batched Pusher broadcast dispatch skipped: " . $pusherEx->getMessage());
            }

            // 2. FCM Push
            if (config('firebase.auto_push_notifications', true)) {
                foreach ($validRecipientUsers as $uid) {
                    try {
                        $u = User::find($uid);
                        if ($u) {
                            \App\Services\Firebase\FcmService::sendToUser(
                                user: $u,
                                title: $title,
                                body: $message,
                                actionUrl: self::resolveUrl($actionUrl),
                                extraData: array_merge($extraData ?? [], [
                                    'module' => (string) $module,
                                    'type' => (string) $type,
                                ])
                            );
                        }
                    } catch (\Throwable $fcmEx) {
                        // ignore
                    }
                }
            }
        });

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

        $userIds = $query->pluck('id')->filter()->unique()->all();

        if (!empty($userIds)) {
            self::sendToUserIds(
                userIds: $userIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: $module,
                type: $type,
                iconClass: $iconClass,
                extraData: $extraData
            );
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
            roles: ['HR', 'Admin', 'Super', 'owner', 'tenant_owner', 'company_admin', 'director'],
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
        $userIds = $query->pluck('user_id')->filter()->unique()->all();
        if (!empty($userIds)) {
            self::sendToUserIds(
                userIds: $userIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: $module,
                type: $type,
                iconClass: $iconClass,
                extraData: $extraData
            );
        }
    }
}
