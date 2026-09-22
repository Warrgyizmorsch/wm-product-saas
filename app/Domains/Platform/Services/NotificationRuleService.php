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
     * @param string|null $actionUrl URL or route name to open when notification is clicked
     * @param Model|null $subjectModel Optional subject Eloquent model
     */
    public static function trigger(
        string $eventKey,
        array $data = [],
        ?string $actionUrl = null,
        ?Model $subjectModel = null
    ): void {
        try {
            $tenantId = (function_exists('tenant_id') && tenant_id()) ? tenant_id() : (auth()->user()?->tenant_id ?? 1);
            $eventDetails = NotificationEventCatalog::getEventDetails($eventKey);
            $module = $eventDetails['module'] ?? 'system';

            // Query configured rules in DB for this tenant
            $rules = NotificationRule::query()
                ->where('tenant_id', $tenantId)
                ->where('event_key', $eventKey)
                ->where('is_active', true)
                ->get();

            if ($rules->isNotEmpty()) {
                foreach ($rules as $rule) {
                    self::executeRule($rule, $data, $actionUrl, $subjectModel, $module);
                }
            } else {
                // If no custom rule defined, use Catalog default roles and templates
                if ($eventDetails) {
                    self::executeDefaultCatalogEvent($eventDetails, $data, $actionUrl, $tenantId, $module);
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
        $url = $actionUrl ?: ($rule->action_route ? self::resolveRoute($rule->action_route, $subjectModel) : null);
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
        int|string $tenantId,
        string $module
    ): void {
        $defaultRoles = $eventDetails['default_roles'] ?? ['Admin'];
        $recipientUserIds = self::resolveRecipientUserIds(
            roles: $defaultRoles,
            userIds: [],
            notifyCreator: false,
            notifyAssignedUser: false,
            data: $data,
            subjectModel: null,
            tenantId: $tenantId
        );

        if (empty($recipientUserIds)) {
            return;
        }

        $title = NotificationRule::interpolate($eventDetails['default_title'] ?? 'System Notification', $data);
        $message = NotificationRule::interpolate($eventDetails['default_body'] ?? '', $data);
        $url = $actionUrl ?: ($eventDetails['action_route'] ?? null);
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
                }
            });
            $roleUserIds = $query->pluck('id')->toArray();
            $finalIds = array_merge($finalIds, $roleUserIds);
        }

        // 3. Dynamic Creator
        if ($notifyCreator) {
            $creatorId = $data['created_by_user_id'] ?? $subjectModel?->created_by ?? null;
            if ($creatorId) {
                $finalIds[] = (int) $creatorId;
            }
        }

        // 4. Dynamic Assigned User
        if ($notifyAssignedUser) {
            $assignedId = $data['assigned_user_id'] ?? $subjectModel?->assigned_to ?? $subjectModel?->user_id ?? null;
            if ($assignedId) {
                $finalIds[] = (int) $assignedId;
            }
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
            if ($subjectModel && $subjectModel->getKey()) {
                return route($routeName, $subjectModel->getKey());
            }
            return route($routeName);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
