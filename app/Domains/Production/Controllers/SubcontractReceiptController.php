<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSubcontractReceipt;
use App\Domains\Production\Services\SubcontractReceiptService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The one receipt page for outsourced work (docs/production_ui_rules_review_2026-11-27.md SF-4 / SF-5). The dispatch
 * slip ("Receive from vendor"), the order screen and the MES floor all redirect here — no popup. Each booked receipt
 * writes a GRN without a PO; "Create vendor bill" opens the Service Bill form (head "Job Work") for that GRN.
 */
class SubcontractReceiptController extends Controller
{
    public function __construct(private readonly SubcontractReceiptService $receipts) {}

    public function create(Request $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $operation = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->with(['vendor', 'order.product', 'routingOperation'])
            ->findOrFail((int) $request->query('operation_id'));
        abort_unless($operation->isOutsourced(), 404);

        $slipsAtVendor = DeliveryChallan::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $operation->id)
            ->whereIn('status', DeliveryChallan::AT_VENDOR_STATUSES)
            ->with('items.product')
            ->orderBy('id')
            ->get();
        $challan = $request->filled('challan_id')
            ? $slipsAtVendor->firstWhere('id', (int) $request->query('challan_id'))
            : $slipsAtVendor->first();

        $needsSlip = $operation->needsDispatchSlip();
        $openWithoutSlip = $needsSlip ? 0.0 : $this->receipts->openWithoutSlip($operation);
        $source = $request->query('source') === ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR
            ? ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR
            : ProductionSubcontractReceipt::SOURCE_CHALLAN;

        $history = ProductionSubcontractReceipt::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $operation->id)
            ->with(['challan', 'receiver', 'grn.vendorBills'])
            ->orderByDesc('id')
            ->get();

        return view('modules.production.subcontract.receipts.create', compact(
            'operation', 'slipsAtVendor', 'challan', 'needsSlip', 'openWithoutSlip', 'source', 'history'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasProductionPermission('production.mes.execute'), 403);
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'operation_id' => 'required|integer',
            'delivery_challan_id' => 'nullable|integer',
            'source' => 'nullable|in:' . ProductionSubcontractReceipt::SOURCE_CHALLAN . ',' . ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR,
            'received_qty' => 'required|numeric|gt:0',
            'rejected_qty' => 'nullable|numeric|min:0',
            'scrapped_qty' => 'nullable|numeric|min:0',
            'returns' => 'nullable|array',
            'returns.*' => 'nullable|numeric|min:0',
            'final' => 'nullable|boolean',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $operation = ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($validated['operation_id']);
        $challan = !empty($validated['delivery_challan_id'])
            ? DeliveryChallan::where('tenant_id', $tenantId)->findOrFail($validated['delivery_challan_id'])
            : null;
        if ($challan) {
            $this->authorize('receive', $challan);
        }

        try {
            $receipt = $this->receipts->receive(
                $operation,
                $validated,
                (int) auth()->id(),
                $validated['source'] ?? ProductionSubcontractReceipt::SOURCE_CHALLAN,
                $challan
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('production.subcontract.receipts.create', array_filter([
            'operation_id' => $operation->id,
            'source' => $validated['source'] ?? null,
        ]))->with('success', $receipt->status === ProductionSubcontractReceipt::STATUS_QC_PENDING
            ? "Receipt {$receipt->receipt_number} recorded — the units wait for this operation's QC on the shop floor; its GRN is written after QC."
            : "Receipt {$receipt->receipt_number} recorded: " . rtrim(rtrim(number_format((float) $receipt->accepted_qty, 4, '.', ''), '0'), '.') . ' good unit(s) booked' . ($receipt->goods_receipt_note_id ? ', GRN written — you can create the vendor bill.' : '.'));
    }
}
