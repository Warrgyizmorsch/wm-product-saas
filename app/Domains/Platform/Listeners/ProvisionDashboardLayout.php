<?php

namespace App\Domains\Platform\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Platform\Models\DashboardLayout;
use App\Domains\Platform\Services\DashboardService;

/** Gives a tenant a default dashboard from the modules its plan includes. Never replaces one an owner has set. */
class ProvisionDashboardLayout
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function handle(TenantProvisioning $event): void
    {
        $exists = DashboardLayout::query()->where('tenant_id', $event->tenantId)->whereNull('user_id')->whereNull('role_id')->exists();

        if ($exists) {
            return;
        }

        DashboardLayout::query()->create([
            'tenant_id' => $event->tenantId,
            'widgets' => $this->dashboard->starterForModules($event->modules),
        ]);
    }
}
