<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeadAssignmentService
{
    /**
     * Get the default lead assignment configuration.
     */
    public static function getDefaultConfig(): array
    {
        return [
            'mode' => 'manual_creator', // 'manual_creator', 'unassigned', 'round_robin'
            'default_owner_id' => null,
            'round_robin_users' => [],
            'last_assigned_user_id' => null,
            'apply_on_web_forms' => true,
            'apply_on_api_imports' => true,
            'apply_on_manual_create' => false,
        ];
    }

    /**
     * Get active assignment configuration for a tenant.
     */
    public static function getConfig(Tenant|int|null $tenant = null): array
    {
        $tenantModel = null;
        if ($tenant instanceof Tenant) {
            $tenantModel = $tenant;
        } elseif (is_numeric($tenant)) {
            $tenantModel = Tenant::find($tenant);
        } else {
            $tenantModel = tenant();
        }

        if (!$tenantModel) {
            return self::getDefaultConfig();
        }

        $settings = is_array($tenantModel->settings) ? $tenantModel->settings : [];
        $config = $settings['lead_assignment'] ?? [];

        return array_merge(self::getDefaultConfig(), $config);
    }

    /**
     * Resolve and assign lead owner based on tenant configuration and channels.
     *
     * @param Lead $lead
     * @param string $channel 'web_form' | 'api_import' | 'manual_create' | 'webhook'
     * @param array $extraData Extra form/API data passed during lead creation
     * @return array ['assigned' => bool, 'owner_id' => ?int, 'reason' => string]
     */
    public function assignLeadOwner(Lead $lead, string $channel = 'web_form', array $extraData = []): array
    {
        $tenantId = $lead->tenant_id ?? (tenant_id() ?? 1);
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ['assigned' => false, 'owner_id' => $lead->lead_owner_id, 'reason' => 'Tenant not found'];
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $config = array_merge(self::getDefaultConfig(), $settings['lead_assignment'] ?? []);

        // Check channel applicability
        if ($channel === 'web_form' && empty($config['apply_on_web_forms'])) {
            return $this->applyManualOrFallback($lead, $config, 'Web-to-Lead assignment disabled');
        }
        if ($channel === 'api_import' && empty($config['apply_on_api_imports'])) {
            return $this->applyManualOrFallback($lead, $config, 'API/Import assignment disabled');
        }
        if ($channel === 'manual_create' && empty($config['apply_on_manual_create'])) {
            if ($lead->lead_owner_id) {
                return ['assigned' => false, 'owner_id' => $lead->lead_owner_id, 'reason' => 'Manual creator assignment retained'];
            }
        }

        $mode = $config['mode'] ?? 'manual_creator';
        $assignedOwnerId = null;
        $reason = '';

        switch ($mode) {
            case 'unassigned':
                $assignedOwnerId = null;
                $reason = 'Assigned to Unassigned Pool (Self-Claim Mode)';
                break;

            case 'round_robin':
                $pool = is_array($config['round_robin_users'] ?? null) ? $config['round_robin_users'] : [];
                $result = $this->getNextRoundRobinUser($tenant, $pool, $config['last_assigned_user_id'] ?? null);
                $assignedOwnerId = $result['user_id'] ?: $config['default_owner_id'];
                $reason = $result['user_id']
                    ? "Round-Robin Auto-Distribution (#{$result['user_name']})"
                    : "Round-Robin Fallback to Default Owner";
                if ($result['user_id']) {
                    $this->updateLastAssignedUser($tenant, $result['user_id']);
                }
                break;

            case 'manual_creator':
            default:
                if ($lead->lead_owner_id) {
                    $assignedOwnerId = $lead->lead_owner_id;
                    $reason = 'Assigned to Creator';
                } elseif (!empty($config['default_owner_id'])) {
                    $assignedOwnerId = (int)$config['default_owner_id'];
                    $reason = 'Assigned to Default CRM Owner';
                } else {
                    $assignedOwnerId = auth()->id() ?: null;
                    $reason = $assignedOwnerId ? 'Assigned to Active User' : 'Unassigned';
                }
                break;
        }

        // Apply owner to lead
        $oldOwnerId = $lead->lead_owner_id;
        $lead->lead_owner_id = $assignedOwnerId;
        $lead->save();

        // Log to Lead History if changed or initially assigned
        if ($assignedOwnerId !== null && $assignedOwnerId != $oldOwnerId) {
            $ownerUser = User::find($assignedOwnerId);
            $ownerName = $ownerUser ? $ownerUser->name : "User #{$assignedOwnerId}";
            try {
                LeadHistory::create([
                    'tenant_id' => $tenant->id,
                    'company_id' => $lead->company_id,
                    'branch_id' => $lead->branch_id,
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id() ?: null,
                    'event_type' => 'assigned',
                    'old_value' => $oldOwnerId ? (User::find($oldOwnerId)?->name ?: 'None') : 'Unassigned',
                    'new_value' => $ownerName,
                    'notes' => "Lead Auto-Assigned: {$reason}",
                ]);
            } catch (\Throwable $e) {
                Log::warning('LeadAssignmentService LeadHistory log error: ' . $e->getMessage());
            }
        }

        return [
            'assigned' => $assignedOwnerId !== null,
            'owner_id' => $assignedOwnerId,
            'reason' => $reason,
        ];
    }

    /**
     * Find next user in round-robin sequence.
     */
    private function getNextRoundRobinUser(Tenant $tenant, array $userIds, ?int $lastAssignedId): array
    {
        // Sanitize and filter only active users of this tenant
        $validUserIds = array_map('intval', array_filter($userIds));
        if (empty($validUserIds)) {
            return ['user_id' => null, 'user_name' => 'None'];
        }

        $activeUsers = User::where('tenant_id', $tenant->id)
            ->whereIn('id', $validUserIds)
            ->get()
            ->keyBy('id');

        $activePool = array_values(array_filter($validUserIds, function ($id) use ($activeUsers) {
            return $activeUsers->has($id);
        }));

        if (empty($activePool)) {
            return ['user_id' => null, 'user_name' => 'None'];
        }

        $nextUserId = null;
        if ($lastAssignedId && in_array($lastAssignedId, $activePool, true)) {
            $currentIndex = array_search($lastAssignedId, $activePool, true);
            $nextIndex = ($currentIndex + 1) % count($activePool);
            $nextUserId = $activePool[$nextIndex];
        } else {
            $nextUserId = $activePool[0];
        }

        $nextUser = $activeUsers->get($nextUserId);
        return [
            'user_id' => $nextUserId,
            'user_name' => $nextUser ? $nextUser->name : "User #{$nextUserId}",
        ];
    }

    /**
     * Update global last assigned user in tenant settings.
     */
    private function updateLastAssignedUser(Tenant $tenant, int $userId): void
    {
        try {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['lead_assignment']['last_assigned_user_id'] = $userId;
            $tenant->update(['settings' => $settings]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update last_assigned_user_id: ' . $e->getMessage());
        }
    }

    /**
     * Fallback helper
     */
    private function applyManualOrFallback(Lead $lead, array $config, string $reason): array
    {
        if ($lead->lead_owner_id) {
            return ['assigned' => false, 'owner_id' => $lead->lead_owner_id, 'reason' => $reason];
        }
        if (!empty($config['default_owner_id'])) {
            $lead->lead_owner_id = (int)$config['default_owner_id'];
            $lead->save();
            return ['assigned' => true, 'owner_id' => $lead->lead_owner_id, 'reason' => 'Default Fallback Owner'];
        }
        return ['assigned' => false, 'owner_id' => null, 'reason' => $reason];
    }
}
