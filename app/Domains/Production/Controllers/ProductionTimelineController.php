<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Http\Request;

class ProductionTimelineController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = ProductionEventTimeline::with(['order', 'operation', 'batch', 'serialNumber', 'machine', 'operator', 'triggerUser'])
            ->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }
        if ($request->filled('operator_id')) {
            $query->where('operator_id', $request->input('operator_id'));
        }
        if ($request->filled('production_order_id')) {
            $query->where('production_order_id', $request->input('production_order_id'));
        }
        if ($request->filled('batch_code')) {
            $query->whereHas('batch', function($q) use ($request) {
                $q->where('batch_number', 'like', '%' . $request->input('batch_code') . '%');
            });
        }
        if ($request->filled('serial_code')) {
            $query->whereHas('serialNumber', function($q) use ($request) {
                $q->where('serial_number', 'like', '%' . $request->input('serial_code') . '%');
            });
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('event_source')) {
            $query->where('event_source', $request->input('event_source'));
        }
        if ($request->filled('date')) {
            $query->whereDate('event_time', $request->input('date'));
        }

        $events = $query->orderBy('event_time', 'desc')->paginate(30);

        // Populate dropdown options
        $machines = Machine::where('tenant_id', $tenantId)->get();
        $orders   = ProductionOrder::where('tenant_id', $tenantId)->get();
        $operators = User::where('tenant_id', $tenantId)->get();

        return view('modules.production.mes.operator.timeline', compact('events', 'machines', 'orders', 'operators'));
    }
}
