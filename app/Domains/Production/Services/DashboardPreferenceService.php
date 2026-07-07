<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionDashboardPreference;

class DashboardPreferenceService
{
    /**
     * Get dashboard preference configurations for a user.
     */
    public function getPreferences(int $tenantId, int $userId, string $dashboardType): array
    {
        $pref = ProductionDashboardPreference::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('dashboard_type', $dashboardType)
            ->first();

        if ($pref) {
            return [
                'widgets'         => $pref->widgets,
                'default_filters' => $pref->default_filters,
                'layout'          => $pref->layout,
            ];
        }

        // Sensible defaults
        $defaultWidgets = match ($dashboardType) {
            'executive'   => ['today_oee', 'today_production', 'today_downtime', 'utilization_charts', 'andon_overview', 'scrap_rejects'],
            'work_center' => ['wc_running_machines', 'queue_depth', 'wc_utilization', 'shift_load', 'bottlenecks'],
            'machine'     => ['current_operation', 'oee_gauges', 'downtime_timeline', 'six_losses'],
            'andon'       => ['machine_state_grid', 'andon_alerts'],
            default       => ['general_summary'],
        };

        return [
            'widgets'         => $defaultWidgets,
            'default_filters' => [],
            'layout'          => 'grid',
        ];
    }

    /**
     * Save dashboard preferences.
     */
    public function savePreferences(int $tenantId, int $userId, string $dashboardType, array $preferences): ProductionDashboardPreference
    {
        return ProductionDashboardPreference::updateOrCreate(
            [
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'dashboard_type' => $dashboardType,
            ],
            [
                'widgets'         => $preferences['widgets'] ?? null,
                'default_filters' => $preferences['default_filters'] ?? null,
                'layout'          => $preferences['layout'] ?? 'grid',
            ]
        );
    }
}
