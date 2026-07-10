<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Repositories\KpiTargetRepositoryInterface;
use App\Domains\Production\Services\KpiTargetService;
use App\Domains\Production\Requests\UpdateKpiTargetsRequest;
use App\Domains\Production\DTO\KpiTargetDTO;
use App\Domains\Production\Models\ProductionKpiTarget;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class KpiTargetController extends Controller
{
    public function __construct(
        private readonly KpiTargetRepositoryInterface $repository,
        private readonly KpiTargetService $service
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ProductionKpiTarget::class);
        $tenantId = require_tenant_id();

        $configuredTargets = $this->repository->getAllForTenant($tenantId)->keyBy('kpi_name');

        $standardKpis = [
            'oee'          => ['label' => 'Overall Equipment Effectiveness (OEE)', 'default' => 85.00, 'unit' => '%'],
            'availability' => ['label' => 'Availability Rate', 'default' => 90.00, 'unit' => '%'],
            'performance'  => ['label' => 'Performance Rate', 'default' => 95.00, 'unit' => '%'],
            'quality'      => ['label' => 'Quality Rate', 'default' => 99.00, 'unit' => '%'],
            'throughput'   => ['label' => 'Throughput Target', 'default' => 100.00, 'unit' => 'units/hr'],
            'utilization'  => ['label' => 'Asset Utilization', 'default' => 80.00, 'unit' => '%'],
            'scrap_rate'   => ['label' => 'Scrap Rate Limit', 'default' => 2.00, 'unit' => '%'],
            'downtime'     => ['label' => 'Max Allowed Downtime', 'default' => 10.00, 'unit' => 'mins/shift'],
        ];

        $targets = [];
        foreach ($standardKpis as $kpiName => $meta) {
            $targets[$kpiName] = [
                'label' => $meta['label'],
                'unit'  => $meta['unit'],
                'value' => $configuredTargets->has($kpiName)
                    ? (float) $configuredTargets->get($kpiName)->target_value
                    : $meta['default']
            ];
        }

        return view('modules.production.intelligence.kpi-targets.index', compact('targets'));
    }

    public function store(UpdateKpiTargetsRequest $request): RedirectResponse
    {
        Gate::authorize('manage', ProductionKpiTarget::class);
        $tenantId = require_tenant_id();

        $data = $request->validated();
        $dtos = [];
        foreach ($data as $kpiName => $targetValue) {
            $dtos[] = new KpiTargetDTO($kpiName, (float) $targetValue);
        }

        try {
            $this->service->updateTargets($tenantId, $dtos);
            return redirect()
                ->route('production.kpi-targets.index')
                ->with('success', 'KPI Target configurations updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Error updating KPI targets: ' . $e->getMessage());
        }
    }
}
