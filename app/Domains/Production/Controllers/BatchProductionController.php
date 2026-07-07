<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\BatchProductionService;
use Illuminate\Http\Request;

class BatchProductionController extends Controller
{
    public function __construct(
        private readonly BatchProductionService $batchService
    ) {}

    public function create(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'production_order_id' => 'required|integer',
            'product_id'          => 'required|integer',
            'planned_quantity'    => 'required|numeric|min:0.0001',
            'expiry_date'         => 'nullable|date',
            'remarks'             => 'nullable|string|max:1000',
        ]);

        try {
            $this->batchService->createBatch(
                $tenantId,
                (int)$request->input('production_order_id'),
                (int)$request->input('product_id'),
                (float)$request->input('planned_quantity'),
                'planned',
                $request->input('expiry_date'),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Batch created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function split(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'parent_batch_id' => 'required|integer',
            'splits'          => 'required|array|min:1',
            'splits.*.planned_quantity' => 'required|numeric|min:0.0001',
            'splits.*.remarks'          => 'nullable|string|max:255',
        ]);

        try {
            $this->batchService->splitBatch(
                $tenantId,
                (int)$request->input('parent_batch_id'),
                $request->input('splits')
            );

            return redirect()->back()->with('success', 'Batch split successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function merge(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'parent_batch_ids'        => 'required|array|min:2',
            'parent_batch_ids.*'      => 'integer',
            'target_planned_quantity' => 'required|numeric|min:0.0001',
            'remarks'                 => 'nullable|string|max:1000',
        ]);

        try {
            $this->batchService->mergeBatches(
                $tenantId,
                $request->input('parent_batch_ids'),
                (float)$request->input('target_planned_quantity'),
                $request->input('remarks')
            );

            return redirect()->back()->with('success', 'Batches merged successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
