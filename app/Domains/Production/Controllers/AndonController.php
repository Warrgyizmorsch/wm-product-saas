<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\DashboardRefreshService;
use App\Domains\Production\Models\Machine;
use Illuminate\Http\Request;

class AndonController extends Controller
{
    public function __construct(
        private readonly DashboardRefreshService $refreshService
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();

        // 1. Get Live Andon state from refresh service
        $andonData = $this->refreshService->refreshAndonBoard($tenantId);

        // 2. Fetch machines with current executions for display
        $machines = Machine::with('workCenter')
            ->where('tenant_id', $tenantId)
            ->get();

        return view('modules.production.intelligence.andon', compact('andonData', 'machines'));
    }
}
