<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\DowntimeService;
use App\Domains\Production\Requests\StartDowntimeRequest;
use App\Domains\Production\Requests\EndDowntimeRequest;

class DowntimeController extends Controller
{
    public function __construct(
        private readonly DowntimeService $downtimeService
    ) {}

    public function start(StartDowntimeRequest $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->downtimeService->startDowntime(
                $tenantId,
                (int)$request->input('machine_id'),
                $request->input('category'),
                $request->input('reason'),
                auth()->id(),
                $request->only(['production_order_id', 'production_order_operation_id', 'remarks'])
            );

            return redirect()->back()->with('success', 'Downtime tracking started.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function end(EndDowntimeRequest $request, int $id)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->downtimeService->endDowntime(
                $tenantId,
                $id,
                auth()->id(),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Downtime event closed.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
