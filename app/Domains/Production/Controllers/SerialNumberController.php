<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Services\SerialNumberService;
use App\Domains\Production\Requests\GenerateSerialNumberRequest;
use App\Domains\Production\Requests\ManualAssignSerialNumberRequest;

class SerialNumberController extends Controller
{
    public function __construct(
        private readonly SerialNumberService $serialService
    ) {}

    public function generate(GenerateSerialNumberRequest $request)
    {
        $this->authorize('manage', ProductionSerialNumber::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->serialService->generateSerials(
                $tenantId,
                (int)$request->input('production_order_id'),
                (int)$request->input('product_id'),
                (int)$request->input('quantity'),
                $request->input('prefix'),
                (int)$request->input('start_num'),
                $request->input('batch_id') ? (int)$request->input('batch_id') : null
            );

            return redirect()->back()->with('success', 'Serial numbers generated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function manualAssign(ManualAssignSerialNumberRequest $request)
    {
        $this->authorize('manage', ProductionSerialNumber::class);

        $tenantId = require_tenant_id();
        $data = $request->validated();

        try {
            $this->serialService->manualAssign(
                $tenantId,
                (int)$request->input('production_order_id'),
                (int)$request->input('product_id'),
                $request->input('serial_number'),
                $request->input('batch_id') ? (int)$request->input('batch_id') : null
            );

            return redirect()->back()->with('success', 'Serial number registered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
