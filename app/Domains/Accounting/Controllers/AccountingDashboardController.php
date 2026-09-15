<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Services\AccountingDashboardService;
use App\Domains\Accounting\Support\DashboardPeriod;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingDashboardController extends Controller
{
    public function __construct(
        private readonly AccountingDashboardService $dashboard,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $tenantId = tenant_id() ?? auth()->user()->tenant_id;
        abort_if($tenantId === null, 404);

        $filters = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(DashboardPeriod::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $today = Carbon::today();
        $period = DashboardPeriod::resolve($filters, $today, $this->dashboard->fiscalYearStart($today));

        return view('modules.accounting.dashboard', $this->dashboard->summary($tenantId, $period, $today) + [
            'presets' => DashboardPeriod::PRESETS,
        ]);
    }
}
