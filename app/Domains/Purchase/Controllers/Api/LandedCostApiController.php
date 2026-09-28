<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Repositories\LandedCostRepository;
use App\Domains\Purchase\Services\LandedCostService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class LandedCostApiController extends Controller
{
    public function __construct(
        private readonly LandedCostRepository $landedCostRepo,
        private readonly LandedCostService $landedCostService,
    ) {}

    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/purchase/landed-costs
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $landedCosts = $this->landedCostRepo->getPaginatedVouchers($tenantId, $request->all(), min((int)$request->input('per_page', 15), 100));

        return response()->json([
            'success' => true,
            'data'    => $landedCosts->items(),
            'meta'    => [
                'current_page' => $landedCosts->currentPage(),
                'last_page'    => $landedCosts->lastPage(),
                'per_page'     => $landedCosts->perPage(),
                'total'        => $landedCosts->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/landed-costs
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'voucher_date'                 => ['required', 'date'],
            'grn_ids'                      => ['required', 'array', 'min:1'],
            'grn_ids.*'                    => ['integer'],
            'expenses'                     => ['required', 'array', 'min:1'],
            'expenses.*.cost_head'         => ['required', 'string'],
            'expenses.*.vendor_id'         => ['nullable', 'integer'],
            'expenses.*.amount'            => ['required', 'numeric', 'min:0.0001'],
            'expenses.*.tax_rate'          => ['nullable', 'numeric', 'min:0'],
            'expenses.*.gst_type'          => ['nullable', 'string', 'in:cgst_sgst,igst,rcm,rcm_cgst_sgst,rcm_igst'],
            'expenses.*.allocation_basis'  => ['required', 'string', 'in:by_qty,by_amount,equal'],
            'notes'                        => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        [$tenantId] = $this->resolveTenantContext();

        try {
            $voucher = $this->landedCostService->createVoucher($tenantId, $validator->validated());
            return response()->json([
                'success' => true,
                'message' => "Landed Cost Voucher {$voucher->voucher_number} created in Draft status.",
                'data'    => $voucher,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create Landed Cost Voucher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/purchase/landed-costs/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $voucher    = $this->landedCostRepo->findById($tenantId, $id);

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Landed Cost Voucher not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $voucher,
        ]);
    }

    /**
     * POST /api/purchase/landed-costs/{id}/post
     */
    public function post(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        try {
            $voucher = $this->landedCostService->postVoucher($tenantId, $id);
            return response()->json([
                'success' => true,
                'message' => "Landed Cost Voucher {$voucher->voucher_number} posted successfully. Stock valuation updated.",
                'data'    => $voucher,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to post voucher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/purchase/landed-costs/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        try {
            $voucher = $this->landedCostService->cancelVoucher($tenantId, $id);
            return response()->json([
                'success' => true,
                'message' => "Landed Cost Voucher {$voucher->voucher_number} cancelled.",
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/purchase/landed-costs/get-grn-items
     */
    public function getGrnItems(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $grnIds     = $request->input('grn_ids', []);

        if (empty($grnIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $items = $this->landedCostService->previewGrnItems($tenantId, array_map('intval', (array)$grnIds));

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }
}
