<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use Illuminate\Http\Request;

class QualityDashboardController extends Controller
{
    public function __construct(
        private readonly ProductionQualityRepositoryInterface $qualityRepository
    ) {
    }

    public function index()
    {
        $this->authorize('view', ProductionQualityInspection::class);
        $tenantId = require_tenant_id();

        $data = $this->qualityRepository->getQualityDashboardKpis($tenantId);

        return view('modules.production.quality.dashboard', $data);
    }
}
