<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\NotificationRule;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NotificationRuleService
{
    /**
     * Trigger notification rules for a given event.
     *
     * @param string $eventKey e.g. 'sales.order.confirmed'
     * @param array $data Dynamic variables e.g. ['doc_no' => 'SO-001', 'customer_name' => 'Acme Corp', 'amount' => '$5,000']
     * @param mixed $actionUrl URL or route name (string), or Eloquent Model, or tenant_id (int)
     * @param mixed $subjectModel Optional subject Eloquent model or user_id (int)
     */
    public static function trigger(
        string $eventKey,
        array $data = [],
        mixed $actionUrl = null,
        mixed $subjectModel = null
    ): void {
        try {
            $resolvedActionUrl = !empty($data['action_url']) ? (string)$data['action_url'] : null;
            $resolvedSubjectModel = null;
            $resolvedTenantId = null;

            // 1. Resolve Argument 3 ($actionUrl OR $tenantId OR Model)
            if ($actionUrl instanceof Model) {
                $resolvedSubjectModel = $actionUrl;
            } elseif (is_string($actionUrl) && !is_numeric($actionUrl)) {
                $resolvedActionUrl = $actionUrl;
            } elseif (is_numeric($actionUrl)) {
                $resolvedTenantId = (int)$actionUrl;
            }

            // 2. Resolve Argument 4 ($subjectModel OR $userId)
            if ($subjectModel instanceof Model) {
                $resolvedSubjectModel = $subjectModel;
            } elseif (is_numeric($subjectModel)) {
                if (empty($data['created_by_user_id'])) {
                    $data['created_by_user_id'] = (int)$subjectModel;
                }
            }

            // 3. Fallback Tenant ID resolution
            if (!$resolvedTenantId) {
                if (!empty($data['tenant_id'])) {
                    $resolvedTenantId = (int)$data['tenant_id'];
                } elseif ($resolvedSubjectModel && isset($resolvedSubjectModel->tenant_id)) {
                    $resolvedTenantId = (int)$resolvedSubjectModel->tenant_id;
                } else {
                    $resolvedTenantId = (function_exists('tenant_id') && tenant_id())
                        ? (int)tenant_id()
                        : (auth()->user()?->tenant_id ? (int)auth()->user()->tenant_id : 1);
                }
            }

            $eventDetails = NotificationEventCatalog::getEventDetails($eventKey);
            $module = $eventDetails['module'] ?? 'system';

            // Query configured rules in DB for this tenant
            $rules = NotificationRule::query()
                ->where('tenant_id', $resolvedTenantId)
                ->where('event_key', $eventKey)
                ->where('is_active', true)
                ->get();

            if ($rules->isNotEmpty()) {
                foreach ($rules as $rule) {
                    self::executeRule($rule, $data, $resolvedActionUrl, $resolvedSubjectModel, $module);
                }
            } else {
                // If no custom rule defined, use Catalog default roles and templates
                if ($eventDetails) {
                    self::executeDefaultCatalogEvent($eventDetails, $data, $resolvedActionUrl, $resolvedSubjectModel, $resolvedTenantId, $module);
                }
            }
        } catch (\Throwable $e) {
            Log::error("NotificationRuleService::trigger failed for event [{$eventKey}]: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Execute a specific database-configured rule.
     */
    private static function executeRule(
        NotificationRule $rule,
        array $data,
        ?string $actionUrl,
        ?Model $subjectModel,
        string $module
    ): void {
        $recipientUserIds = self::resolveRecipientUserIds(
            roles: $rule->recipient_roles ?? [],
            userIds: $rule->recipient_user_ids ?? [],
            notifyCreator: (bool) $rule->notify_creator,
            notifyAssignedUser: (bool) $rule->notify_assigned_user,
            data: $data,
            subjectModel: $subjectModel,
            tenantId: $rule->tenant_id
        );

        if (empty($recipientUserIds)) {
            return;
        }

        $title = $rule->renderTitle($data);
        $message = $rule->renderBody($data);
        $url = $actionUrl ?: ($data['action_url'] ?? ($rule->action_route ? self::resolveRoute($rule->action_route, $subjectModel) : null));
        $icon = $rule->icon_class ?: 'feather-bell';

        // Dispatch In-App header bell notifications
        NotificationService::sendToUserIds(
            userIds: $recipientUserIds,
            title: $title,
            message: $message,
            actionUrl: $url,
            module: $rule->module ?: $module,
            type: 'alert',
            iconClass: $icon,
            extraData: ['rule_id' => $rule->id, 'tenant_id' => $rule->tenant_id]
        );
    }

    /**
     * Execute default fallback from catalog when admin hasn't customized it yet.
     */
    private static function executeDefaultCatalogEvent(
        array $eventDetails,
        array $data,
        ?string $actionUrl,
        ?Model $subjectModel,
        int|string $tenantId,
        string $module
    ): void {
        $defaultRoles = $eventDetails['default_roles'] ?? ['Admin'];
        $recipientUserIds = self::resolveRecipientUserIds(
            roles: $defaultRoles,
            userIds: [],
            notifyCreator: false,
            notifyAssignedUser: (bool) ($eventDetails['notify_assigned_user'] ?? true),
            data: $data,
            subjectModel: $subjectModel,
            tenantId: $tenantId
        );

        if (empty($recipientUserIds)) {
            return;
        }

        $title = NotificationRule::interpolate($eventDetails['default_title'] ?? 'System Notification', $data);
        $message = NotificationRule::interpolate($eventDetails['default_body'] ?? '', $data);
        $url = $actionUrl ?: ($data['action_url'] ?? (isset($eventDetails['action_route']) ? self::resolveRoute($eventDetails['action_route'], $subjectModel) : null));
        $icon = $eventDetails['icon'] ?? 'feather-bell';

        NotificationService::sendToUserIds(
            userIds: $recipientUserIds,
            title: $title,
            message: $message,
            actionUrl: $url,
            module: $module,
            type: 'alert',
            iconClass: $icon,
            extraData: ['tenant_id' => $tenantId]
        );
    }

    /**
     * Resolve all recipient User IDs based on roles, users list, and dynamic context.
     */
    public static function resolveRecipientUserIds(
        array $roles,
        array $userIds,
        bool $notifyCreator,
        bool $notifyAssignedUser,
        array $data,
        ?Model $subjectModel,
        int|string $tenantId
    ): array {
        $finalIds = [];

        // 1. Direct User IDs
        foreach ($userIds as $uid) {
            if (!empty($uid)) {
                $finalIds[] = (int) $uid;
            }
        }

        // 2. Query Users with Matching Roles in this Tenant
        if (!empty($roles)) {
            $query = User::query()->where('tenant_id', $tenantId);
            $query->where(function ($q) use ($roles) {
                foreach ($roles as $role) {
                    $cleanRole = trim($role);
                    if (empty($cleanRole)) continue;

                    $q->orWhere('role', 'like', "%{$cleanRole}%")
                      ->orWhereHas('primaryRole', function ($rq) use ($cleanRole) {
                          $rq->where('name', 'like', "%{$cleanRole}%");
                      })
                      ->orWhereHas('roles', function ($rq) use ($cleanRole) {
                          $rq->where('name', 'like', "%{$cleanRole}%");
                      });

                    if (strcasecmp($cleanRole, 'Employee') === 0) {
                        $q->orWhereHas('employee');
                    }
                }
            });
            $roleUserIds = $query->pluck('id')->toArray();
            $finalIds = array_merge($finalIds, $roleUserIds);
        }

        // 3. Dynamic Creator
        if ($notifyCreator) {
            $creatorId = $data['created_by_user_id'] ?? $data['user_id'] ?? $subjectModel?->created_by ?? null;
            if (!$creatorId && $subjectModel && isset($subjectModel->employee_id)) {
                $creatorId = $subjectModel->employee?->user_id;
            }
            if (!$creatorId && $subjectModel && method_exists($subjectModel, 'documentable') && $subjectModel->documentable instanceof \App\Domains\HRMS\Models\Employee) {
                $creatorId = $subjectModel->documentable->user_id;
            }
            if (!$creatorId && !empty($data['employee_id'])) {
                $creatorId = \App\Domains\HRMS\Models\Employee::where('tenant_id', $tenantId)->find($data['employee_id'])?->user_id;
            }
            if ($creatorId) {
                $finalIds[] = (int) $creatorId;
            }
        }

        // 4. Dynamic Assigned User
        if ($notifyAssignedUser) {
            $assignedId = $data['assigned_user_id'] ?? $subjectModel?->assigned_to ?? $subjectModel?->user_id ?? null;
            if (!$assignedId && $subjectModel && method_exists($subjectModel, 'assignedAgent')) {
                $assignedId = $subjectModel->assignedAgent?->user_id;
            }
            if (!$assignedId && $subjectModel && method_exists($subjectModel, 'manager')) {
                $assignedId = $subjectModel->manager?->user_id;
            }
            if (!$assignedId && $subjectModel && isset($subjectModel->employee)) {
                $assignedId = $subjectModel->employee?->reportingManager?->user_id;
            }
            if (!$assignedId && $subjectModel instanceof \App\Domains\HRMS\Models\Employee) {
                $assignedId = $subjectModel->reportingManager?->user_id;
            }
            if (!$assignedId && $subjectModel instanceof \App\Domains\HRMS\Models\Document) {
                $assignedId = $subjectModel->requested_by_id;
            }
            if ($assignedId) {
                $finalIds[] = (int) $assignedId;
            }

            // Dynamic Account Manager for CRM / Sales models
            $accountManagerId = $data['account_manager_id'] ?? null;
            if (!$accountManagerId && $subjectModel && method_exists($subjectModel, 'account')) {
                $accountManagerId = $subjectModel->account?->owner_id;
            }
            if ($accountManagerId) {
                $finalIds[] = (int) $accountManagerId;
            }
        }

        // 5. Exclude Users (prevent duplicate notification if already sent directly)
        if (!empty($data['exclude_user_ids'])) {
            $exclude = is_array($data['exclude_user_ids']) ? $data['exclude_user_ids'] : [$data['exclude_user_ids']];
            $finalIds = array_diff($finalIds, array_map('intval', $exclude));
        }

        return array_values(array_unique(array_filter($finalIds)));
    }

    /**
     * Resolve a named route with subject model ID if present.
     */
    private static function resolveRoute(string $routeName, ?Model $subjectModel): ?string
    {
        if (!\Illuminate\Support\Facades\Route::has($routeName)) {
            return $routeName;
        }

        try {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
            $paramNames = $route ? $route->parameterNames() : [];

            if (!empty($paramNames)) {
                if ($subjectModel && $subjectModel->getKey()) {
                    return route($routeName, $subjectModel->getKey());
                }
                // Parameter required but no subject model key available: fallback to index
                if (str_ends_with($routeName, '.show')) {
                    $indexRoute = substr($routeName, 0, -5) . '.index';
                    if (\Illuminate\Support\Facades\Route::has($indexRoute)) {
                        return route($indexRoute);
                    }
                }
                return route($routeName, 1);
            }

            return route($routeName);
        } catch (\Throwable $e) {
            // If route required parameter but none was provided, attempt fallback to index route
            if (str_ends_with($routeName, '.show')) {
                $indexRoute = substr($routeName, 0, -5) . '.index';
                if (\Illuminate\Support\Facades\Route::has($indexRoute)) {
                    try {
                        return route($indexRoute);
                    } catch (\Throwable) {}
                }
            }
            return null;
        }
    }
}
