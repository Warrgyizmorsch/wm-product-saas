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
            // FRS 2.5 step 3: territory / segment / product / source / workload / account-owner routing
            'prefer_account_owner' => false,
            'rules' => [],
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

        // Rule based routing (account owner first, then the first matching rule) overrides the base mode.
        $routed = $this->resolveByRules($lead, $tenant, $config);
        if ($routed !== null) {
            $mode = '__routed';
        }

        switch ($mode) {
            case '__routed':
                $assignedOwnerId = $routed['owner_id'];
                $reason = $routed['reason'];
                break;

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

        // FRS 2.5 / C-01: Start Response-Time SLA Clock on assignment
        if ($assignedOwnerId !== null) {
            $lead->assigned_at = now();
            $leadPolicy = $settings['crm_lead_policy'] ?? [];
            $slaMinutes = (int)($leadPolicy['response_sla_minutes'] ?? 60);
            $lead->response_sla_minutes = $slaMinutes > 0 ? $slaMinutes : 60;
            $lead->response_sla_breached = false;
            $lead->sla_escalated_at = null;
        }

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

            try {
                LeadService::notifyLeadAssigned($lead, $assignedOwnerId, auth()->id());
            } catch (\Throwable $e) {
                Log::warning('LeadAssignmentService notifyLeadAssigned error: ' . $e->getMessage());
            }
        }

        if ($assignedOwnerId !== null) {
            app(LeadNextActionService::class)->ensureNextAction($lead->fresh());
        }

        return [
            'assigned' => $assignedOwnerId !== null,
            'owner_id' => $assignedOwnerId,
            'reason' => $reason,
        ];
    }

    /**
     * FRS 2.5 step 3: route a lead by account owner and by configured rules.
     * A rule may filter on territory (state / city / country), segment, source and product keyword,
     * and picks its owner from a pool by workload (fewest open leads), round-robin or first.
     *
     * @return array{owner_id:int, reason:string}|null
     */
    private function resolveByRules(Lead $lead, Tenant $tenant, array $config): ?array
    {
        // 1. Existing account owner (a returning company stays with its account manager)
        if (!empty($config['prefer_account_owner'])) {
            $account = $this->findExistingAccount($lead);
            if ($account && !empty($account->owner_id) && User::where('tenant_id', $tenant->id)->where('id', $account->owner_id)->exists()) {
                return ['owner_id' => (int) $account->owner_id, 'reason' => "Account Owner of {$account->name}"];
            }
        }

        // 2. Configured rules, first match wins
        foreach ((array) ($config['rules'] ?? []) as $index => $rule) {
            if (!is_array($rule) || (isset($rule['active']) && !$rule['active'])) {
                continue;
            }
            if (!$this->ruleMatches($lead, $rule)) {
                continue;
            }
            $pool = array_values(array_filter(array_map('intval', (array) ($rule['owner_ids'] ?? []))));
            $validIds = $pool ? User::where('tenant_id', $tenant->id)->whereIn('id', $pool)->pluck('id')->map(fn ($i) => (int) $i)->all() : [];
            $pool = array_values(array_intersect($pool, $validIds));
            if (empty($pool)) {
                continue;
            }

            $strategy = $rule['strategy'] ?? 'least_loaded';
            if ($strategy === 'round_robin') {
                $last = $rule['last_user_id'] ?? null;
                $pos = $last ? array_search((int) $last, $pool, true) : false;
                $ownerId = $pool[$pos === false ? 0 : (($pos + 1) % count($pool))];
                $this->persistRuleCursor($tenant, $index, $ownerId);
            } elseif ($strategy === 'first') {
                $ownerId = $pool[0];
            } else {
                $ownerId = $this->leastLoadedOwner($tenant->id, $pool);
            }

            $label = $rule['name'] ?? ('Rule #' . ($index + 1));
            return ['owner_id' => $ownerId, 'reason' => "Assignment rule '{$label}' ({$strategy})"];
        }

        return null;
    }

    private function ruleMatches(Lead $lead, array $rule): bool
    {
        $eq = fn ($a, $b) => strcasecmp(trim((string) $a), trim((string) $b)) === 0;

        if (!empty($rule['territory'])) {
            $t = (string) $rule['territory'];
            if (!$eq($lead->state, $t) && !$eq($lead->city, $t) && !$eq($lead->country, $t)) {
                return false;
            }
        }
        if (!empty($rule['segment']) && !$eq($lead->segment, $rule['segment'])) {
            return false;
        }
        if (!empty($rule['source']) && !$eq($lead->source, $rule['source'])) {
            return false;
        }
        if (!empty($rule['product'])) {
            $needle = mb_strtolower((string) $rule['product']);
            $haystack = mb_strtolower((string) $lead->requirement);
            foreach ((array) ($lead->product_items ?? []) as $item) {
                $haystack .= ' ' . mb_strtolower((string) (is_array($item) ? ($item['name'] ?? $item['product_name'] ?? '') : $item));
            }
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function leastLoadedOwner(int $tenantId, array $pool): int
    {
        $counts = Lead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('lead_owner_id', $pool)
            ->whereNotIn('status', ['Won', 'Lost', 'Converted'])
            ->whereNull('deleted_at')
            ->selectRaw('lead_owner_id, COUNT(*) as c')
            ->groupBy('lead_owner_id')
            ->pluck('c', 'lead_owner_id');

        $best = $pool[0];
        $bestCount = PHP_INT_MAX;
        foreach ($pool as $id) {
            $c = (int) ($counts[$id] ?? 0);
            if ($c < $bestCount) {
                $best = $id;
                $bestCount = $c;
            }
        }

        return (int) $best;
    }

    private function findExistingAccount(Lead $lead): ?\App\Domains\CRM\Models\CrmAccount
    {
        $query = \App\Domains\CRM\Models\CrmAccount::where('tenant_id', $lead->tenant_id);
        $query->where(function ($q) use ($lead) {
            $any = false;
            if (!empty($lead->email)) {
                $q->where('email', $lead->email);
                $any = true;
            }
            if (!empty($lead->company_name) && strcasecmp((string) $lead->company_name, 'Individual Customer') !== 0) {
                $any ? $q->orWhere('name', $lead->company_name) : $q->where('name', $lead->company_name);
                $any = true;
            }
            if (!$any) {
                $q->whereRaw('1 = 0');
            }
        });

        return $query->first();
    }

    private function persistRuleCursor(Tenant $tenant, int|string $index, int $ownerId): void
    {
        try {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            if (isset($settings['lead_assignment']['rules'][$index])) {
                $settings['lead_assignment']['rules'][$index]['last_user_id'] = $ownerId;
                $tenant->update(['settings' => $settings]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to persist assignment rule cursor: ' . $e->getMessage());
        }
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
            $lead->assigned_at = now();
            $tenantSettings = is_array(Tenant::find($lead->tenant_id)?->settings) ? Tenant::find($lead->tenant_id)->settings : [];
            $leadPolicy = $tenantSettings['crm_lead_policy'] ?? [];
            $slaMinutes = (int)($leadPolicy['response_sla_minutes'] ?? 60);
            $lead->response_sla_minutes = $slaMinutes > 0 ? $slaMinutes : 60;
            $lead->response_sla_breached = false;
            $lead->sla_escalated_at = null;
            $lead->save();
            return ['assigned' => true, 'owner_id' => $lead->lead_owner_id, 'reason' => 'Default Fallback Owner'];
        }
        return ['assigned' => false, 'owner_id' => null, 'reason' => $reason];
    }
}
