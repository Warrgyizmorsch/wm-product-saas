<?php

namespace App\Domains\Purchase\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Repositories\LandedCostRepository;
use App\Domains\Purchase\Services\LandedCostService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LandedCostController extends Controller
{
    public function __construct(
        private readonly LandedCostRepository $landedCostRepo,
        private readonly LandedCostService $landedCostService,
    ) {}

    public function index(Request $request)
    {
        $tenantId = require_tenant_id();
        $landedCosts = $this->landedCostRepo->getPaginatedVouchers($tenantId, $request->all(), 15);

        return view('modules.purchase.landed-costs.index', compact('landedCosts'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();
        $grns = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->orderBy('id', 'desc')
            ->get();

        $vendors = Vendor::where('tenant_id', $tenantId)->get();
        $transporterVendorIds = \App\Domains\Platform\Models\Transporter::where('tenant_id', $tenantId)
            ->whereNotNull('vendor_id')
            ->pluck('vendor_id')
            ->toArray();

        $vendors->each(function ($v) use ($transporterVendorIds) {
            $nameLower = strtolower($v->name);
            $v->is_transporter = in_array($v->id, $transporterVendorIds)
                || str_contains($nameLower, 'logistics')
                || str_contains($nameLower, 'transport')
                || str_contains($nameLower, 'express')
                || str_contains($nameLower, 'carrier');
            $v->type_label = $v->is_transporter ? 'Transporter / Logistics' : 'Supplier / Vendor';
        });

        return view('modules.purchase.landed-costs.create', compact('grns', 'vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'voucher_date' => 'required|date',
            'grn_ids' => 'required|array|min:1',
            'grn_ids.*' => 'integer|exists:goods_receipt_notes,id',
            'expenses' => 'required|array|min:1',
            'expenses.*.cost_head' => 'required|string',
            'expenses.*.vendor_id' => 'nullable|integer',
            'expenses.*.amount' => 'required|numeric|min:0.0001',
            'expenses.*.tax_rate' => 'nullable|numeric|min:0',
            'expenses.*.gst_type' => 'nullable|string|in:cgst_sgst,igst,rcm',
            'expenses.*.is_rcm' => 'nullable',
            'expenses.*.allocation_basis' => 'required|string|in:by_qty,by_amount,equal',
            'notes' => 'nullable|string',
        ]);

        $tenantId = require_tenant_id();

        try {
            $voucher = $this->landedCostService->createVoucher($tenantId, $request->all());
            return redirect()->route('purchase.landed-costs.show', $voucher->id)
                ->with('success', "Landed Cost Voucher {$voucher->voucher_number} created in Draft status.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create Landed Cost Voucher: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();
        $voucher = $this->landedCostRepo->findById($tenantId, $id);

        if (!$voucher) {
            abort(404, 'Landed Cost Voucher not found.');
        }

        return view('modules.purchase.landed-costs.show', compact('voucher'));
    }

    public function post(int $id)
    {
        $tenantId = require_tenant_id();

        try {
            $voucher = $this->landedCostService->postVoucher($tenantId, $id);
            return redirect()->back()->with('success', "Landed Cost Voucher {$voucher->voucher_number} posted successfully. Stock valuation updated.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to post voucher: ' . $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $tenantId = require_tenant_id();

        try {
            $voucher = $this->landedCostService->cancelVoucher($tenantId, $id);
            return redirect()->route('purchase.landed-costs.index')
                ->with('success', "Landed Cost Voucher {$voucher->voucher_number} cancelled.");
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getGrnItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $grnIds = $request->input('grn_ids', []);

        if (empty($grnIds)) {
            return response()->json(['items' => []]);
        }

        $items = $this->landedCostService->previewGrnItems($tenantId, array_map('intval', (array) $grnIds));
        return response()->json(['items' => $items]);
    }
}
