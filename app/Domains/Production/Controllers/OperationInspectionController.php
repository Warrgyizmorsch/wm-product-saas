<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\OrderOperationQualityService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * QD (user 2026-11-28): an order operation's QC is done on its inspection page, not in a shop-floor popup. The MES board
 * and the operator screen redirect here. The page lists every inspection record of the operation (one record = one
 * quantity with one result) and the open record(s) to perform with the operation's plans.
 */
class OperationInspectionController extends Controller
{
    public function __construct(private readonly QualityInspectionService $inspectionService)
    {
    }

    public function show(Request $request, int $op): View
    {
        $this->authorizeQc();
        $tenantId = require_tenant_id();
        $orderOp = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->with(['order.product', 'sourceProduct', 'routingOperation', 'vendor'])
            ->findOrFail($op);
        abort_unless($this->inspectionService->operationNeedsQc($orderOp), 404, 'This operation does not need QC.');

        // QD-02: older orders (or an operation started before this change) get their open record here
        $this->inspectionService->openOperationInspection($orderOp, auth()->id());

        $records = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $orderOp->id)
            ->with(['auditor', 'results.parameter', 'planLinks'])
            ->orderBy('id')
            ->get();
        $open = $records->where('status', 'draft')->values();
        $done = $records->where('status', '!=', 'draft')->values();
        $active = $open->firstWhere('id', (int) $request->query('inspection')) ?? $open->first();

        $checklistPlans = app(OrderOperationQualityService::class)->checklistFor($orderOp)
            ->map(fn ($link) => ['plan' => $link->plan, 'is_mandatory' => (bool) $link->is_mandatory, 'source' => $link->source])
            ->filter(fn ($entry) => $entry['plan'] !== null)
            ->values();

        $waiting = app(MesExecutionService::class)->getPendingQcQuantity($orderOp->id);
        $target = (float) ($orderOp->target_produced_qty > 0 ? $orderOp->target_produced_qty : ($orderOp->order->quantity_ordered ?? 0));

        return view('modules.production.quality.inspections.operation', [
            'orderOp' => $orderOp,
            'order' => $orderOp->order,
            'records' => $done,
            'openRecords' => $open,
            'active' => $active,
            'checklistPlans' => $checklistPlans,
            'waiting' => $waiting,
            'target' => $target,
            'passedQty' => (float) $done->where('result', 'passed')->sum('inspected_quantity'),
            'failedQty' => (float) $done->where('result', 'failed')->sum('inspected_quantity'),
            'source' => $request->query('source'),
        ]);
    }

    /** QD-03: another open record for the same operation, when the process needs it */
    public function add(Request $request, int $op): RedirectResponse
    {
        $this->authorizeQc();
        $orderOp = ProductionOrderOperation::where('tenant_id', require_tenant_id())->findOrFail($op);
        $inspection = $this->inspectionService->openOperationInspection($orderOp, auth()->id(), true);

        return redirect()->route('production.quality.operation-inspection.show', ['op' => $orderOp->id, 'inspection' => $inspection?->id, 'source' => $request->input('source')])
            ->with('success', $inspection ? "Inspection {$inspection->inspection_number} added." : 'This operation does not need QC.');
    }

    public function perform(Request $request, int $inspection): RedirectResponse
    {
        $this->authorizeQc();
        $record = ProductionQualityInspection::where('tenant_id', require_tenant_id())->findOrFail($inspection);
        abort_unless($record->production_order_operation_id, 404);

        $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'remarks' => 'nullable|string|max:1000',
            'parameter_values' => 'nullable|array',
            'parameter_values.*' => 'nullable|array',
            'parameter_values.*.*' => 'nullable|string|max:1000',
            'inspected_plans' => 'nullable|array',
            'inspected_plans.*' => 'integer',
            'result' => 'nullable|in:passed,failed', // only for an operation without a quality plan
        ]);

        $back = ['op' => $record->production_order_operation_id, 'source' => $request->input('source')];
        try {
            $done = $this->inspectionService->performOperationInspection($record, [
                'quantity' => (float) $request->input('quantity'),
                'parameter_values' => (array) $request->input('parameter_values', []),
                'inspected_plans' => (array) $request->input('inspected_plans', []),
                'remarks' => $request->input('remarks'),
                'result' => $request->input('result'),
            ], (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('production.quality.operation-inspection.show', $back + ['inspection' => $record->id])
                ->withInput()->with('error', $e->getMessage());
        }

        $qty = rtrim(rtrim(number_format((float) $done->inspected_quantity, 4, '.', ''), '0'), '.');

        return redirect()->route('production.quality.operation-inspection.show', $back)
            ->with($done->result === 'passed' ? 'success' : 'warning', $done->result === 'passed'
                ? "Inspection {$done->inspection_number}: {$qty} unit(s) passed and moved to the next step."
                : "Inspection {$done->inspection_number}: {$qty} unit(s) failed. They are counted as rejected (NCR raised).");
    }

    private function authorizeQc(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasProductionPermission('production.mes.execute') || $user->hasProductionPermission('production.quality.manage')), 403);
    }
}
