<?php

namespace App\Domains\Platform\Controllers\Admin;

use App\Domains\Platform\Models\DemoRequest;
use App\Domains\Platform\Models\PlatformAuditLog;
use App\Domains\Platform\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Console landing page. Phase 0 shows tenant counts, open demo requests and
 * recent console activity; revenue KPIs (MRR/ARR/churn) come in Phase 1.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantService $tenants, AccessService $access): View
    {
        return view('modules.platform.admin.dashboard', [
            'canManageLeads' => $access->allows($request->user(), 'platform.leads.manage'),
            'canManageTenants' => $access->allows($request->user(), 'platform.tenants.manage'),
            'summary' => $tenants->summary(),
            'newDemoRequests' => DemoRequest::query()->where('status', DemoRequest::STATUS_NEW)->count(),
            'trialEndingSoon' => Tenant::query()
                ->where('status', Tenant::STATUS_TRIAL)
                ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
                ->orderBy('trial_ends_at')
                ->limit(8)
                ->get(['id', 'name', 'slug', 'trial_ends_at']),
            'recentActivity' => PlatformAuditLog::query()
                ->with(['user:id,name', 'tenant:id,name'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
