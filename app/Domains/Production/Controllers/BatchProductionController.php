<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Requests\CreateBatchRequest;
use App\Domains\Production\Requests\SplitBatchRequest;
use App\Domains\Production\Requests\MergeBatchesRequest;

class BatchProductionController extends Controller
{
    public function __construct(
        private readonly BatchProductionService $batchService
    ) {}

    public function create(CreateBatchRequest $request)
    {
        $this->authorize('manage', ProductionBatch::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

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

    public function split(SplitBatchRequest $request)
    {
        $this->authorize('manage', ProductionBatch::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

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

    public function merge(MergeBatchesRequest $request)
    {
        $this->authorize('manage', ProductionBatch::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

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
