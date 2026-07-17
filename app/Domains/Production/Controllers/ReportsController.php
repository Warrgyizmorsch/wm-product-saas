<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\ReportingService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportService
    ) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $machines = Machine::where('tenant_id', $tenantId)->get();
        $workCenters = WorkCenter::where('tenant_id', $tenantId)->get();

        return view('modules.production.intelligence.reports', compact('machines', 'workCenters'));
    }

    public function show(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only(['date_start', 'date_end', 'machine_id', 'work_center_id']);

        $reportData = match ($type) {
            'machine'      => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'  => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'     => $this->reportService->generateDowntimeReport($tenantId, $filters),
            default        => abort(404),
        };

        // If print view is requested, we pass it to a simple print layout
        $print = $request->has('print');

        return view('modules.production.intelligence.reports-detail', compact('reportData', 'type', 'print'));
    }

    public function export(Request $request, string $type)
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.intelligence.view'), 403);
        $tenantId = require_tenant_id();
        $filters = $request->only(['date_start', 'date_end', 'machine_id', 'work_center_id']);

        $reportData = match ($type) {
            'machine'      => $this->reportService->generateMachineReport($tenantId, $filters),
            'work-center'  => $this->reportService->generateWorkCenterReport($tenantId, $filters),
            'downtime'     => $this->reportService->generateDowntimeReport($tenantId, $filters),
            default        => abort(404),
        };

        $filename = "report_{$type}_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($type, $reportData) {
            $file = fopen('php://output', 'w');

            if ($type === 'machine') {
                fputcsv($file, ['Machine Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)', 'Total Produced', 'Good Quantity', 'Downtime (min)']);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['name'],
                        $row['code'],
                        $row['oee'],
                        $row['availability'],
                        $row['performance'],
                        $row['quality'],
                        $row['total_produced'],
                        $row['good_quantity'],
                        $row['downtime_minutes'],
                    ]);
                }
            } elseif ($type === 'work-center') {
                fputcsv($file, ['Work Center Name', 'Code', 'OEE (%)', 'Availability (%)', 'Performance (%)', 'Quality (%)']);
                foreach ($reportData['data'] as $row) {
                    fputcsv($file, [
                        $row['name'],
                        $row['code'],
                        $row['oee'],
                        $row['availability'],
                        $row['performance'],
                        $row['quality'],
                    ]);
                }
            } elseif ($type === 'downtime') {
                fputcsv($file, ['Machine', 'Reason', 'Category', 'Start Time', 'End Time', 'Duration (min)', 'Status']);
                foreach ($reportData['downtimes'] as $row) {
                    fputcsv($file, [
                        $row->machine->name ?? 'Unknown',
                        $row->reason,
                        $row->category,
                        $row->start_time->toDateTimeString(),
                        $row->end_time ? $row->end_time->toDateTimeString() : 'N/A',
                        $row->duration_minutes,
                        ucfirst($row->status),
                    ]);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
