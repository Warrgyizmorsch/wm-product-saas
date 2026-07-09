<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScanLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionScanLog::with('user')->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('scanned_by')) {
            $query->where('scanned_by', $request->input('scanned_by'));
        }

        if ($request->filled('date_start')) {
            $query->whereDate('scanned_at', '>=', $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->whereDate('scanned_at', '<=', $request->input('date_end'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('device_identifier', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search);
                  });
            });
        }

        $logs = $query->orderBy('scanned_at', 'desc')->paginate(15)->withQueryString();
        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('modules.production.mes.scan-logs.index', compact('logs', 'users'));
    }

    public function show(int $id): View
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $log = ProductionScanLog::with('user')->where('tenant_id', $tenantId)->findOrFail($id);

        // Resolve entity info
        $entityInfo = null;
        switch ($log->entity_type) {
            case 'order':
                $order = ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($log->entity_id);
                if ($order) {
                    $entityInfo = [
                        'code' => $order->order_number,
                        'name' => $order->product?->name ?? '—',
                        'qty'  => number_format($order->quantity_ordered, 2) . ' ' . ($order->product?->uom?->code ?? 'PCS'),
                        'status' => $order->status,
                    ];
                }
                break;
            case 'batch':
                $batch = ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($log->entity_id);
                if ($batch) {
                    $entityInfo = [
                        'code' => $batch->batch_number,
                        'name' => $batch->product?->name ?? '—',
                        'qty'  => number_format($batch->planned_quantity, 2),
                        'status' => $batch->status,
                    ];
                }
                break;
            case 'serial':
                $serial = ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($log->entity_id);
                if ($serial) {
                    $entityInfo = [
                        'code' => $serial->serial_number,
                        'name' => $serial->product?->name ?? '—',
                        'qty'  => '1.00',
                        'status' => $serial->status,
                    ];
                }
                break;
        }

        return view('modules.production.mes.scan-logs.show', compact('log', 'entityInfo'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $query = ProductionScanLog::with('user')->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('scanned_by')) {
            $query->where('scanned_by', $request->input('scanned_by'));
        }

        if ($request->filled('date_start')) {
            $query->whereDate('scanned_at', '>=', $request->input('date_start'));
        }

        if ($request->filled('date_end')) {
            $query->whereDate('scanned_at', '<=', $request->input('date_end'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('device_identifier', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search);
                  });
            });
        }

        $logs = $query->orderBy('scanned_at', 'desc')->get();

        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=production_scan_logs_' . date('Ymd_His') . '.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Scan Type', 'Entity Type', 'Entity ID', 'Entity Number', 'Scanned By', 'Device ID', 'Scanned At']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    ucfirst($log->scan_type),
                    ucfirst($log->entity_type),
                    $log->entity_id,
                    $log->getEntityCode(),
                    $log->user ? $log->user->name : 'System',
                    $log->device_identifier ?: 'N/A',
                    $log->scanned_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
