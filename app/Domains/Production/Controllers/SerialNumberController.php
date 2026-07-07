<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\SerialNumberService;
use Illuminate\Http\Request;

class SerialNumberController extends Controller
{
    public function __construct(
        private readonly SerialNumberService $serialService
    ) {}

    public function generate(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'production_order_id' => 'required|integer',
            'product_id'          => 'required|integer',
            'quantity'            => 'required|integer|min:1',
            'prefix'              => 'required|string|max:50',
            'start_num'           => 'required|integer|min:1',
            'batch_id'            => 'nullable|integer',
        ]);

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

    public function manualAssign(Request $request)
    {
        $tenantId = require_tenant_id();
        $request->validate([
            'production_order_id' => 'required|integer',
            'product_id'          => 'required|integer',
            'serial_number'       => 'required|string|max:100',
            'batch_id'            => 'nullable|integer',
        ]);

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
