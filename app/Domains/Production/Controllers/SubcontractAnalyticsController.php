<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Services\SubcontractPerformanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubcontractAnalyticsController extends Controller
{
    public function __construct(
        protected SubcontractPerformanceService $performanceService
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $filters = [
            'vendor_id' => $request->query('vendor_id'),
            'product_id' => $request->query('product_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'period' => $request->query('period', 'all'),
        ];

        // Apply pre-set periods if specified
        if ($request->filled('period')) {
            switch ($request->period) {
                case 'this_month':
                    $filters['date_from'] = date('Y-m-01');
                    $filters['date_to'] = date('Y-m-t');
                    break;
                case 'last_month':
                    $filters['date_from'] = date('Y-m-01', strtotime('first day of last month'));
                    $filters['date_to'] = date('Y-m-t', strtotime('last day of last month'));
                    break;
                case 'this_quarter':
                    $month = date('n');
                    $quarter = ceil($month / 3);
                    $startMonth = str_pad(($quarter - 1) * 3 + 1, 2, '0', STR_PAD_LEFT);
                    $filters['date_from'] = date("Y-{$startMonth}-01");
                    $filters['date_to'] = date('Y-m-t', strtotime("+2 months", strtotime($filters['date_from'])));
                    break;
                case 'this_year':
                    $filters['date_from'] = date('Y-01-01');
                    $filters['date_to'] = date('Y-12-31');
                    break;
            }
        }

        $overall = $this->performanceService->getOverallMetrics($tenantId, $filters);
        $comparison = $this->performanceService->getVendorComparisonTable($tenantId, $filters);
        $delayedOps = $this->performanceService->getDelayedOperationsReport($tenantId, $filters);

        $vendors = Vendor::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $products = Product::where('tenant_id', $tenantId)->get();

        return view('modules.production.subcontract.analytics.index', compact(
            'overall',
            'comparison',
            'delayedOps',
            'vendors',
            'products',
            'filters'
        ));
    }
}
