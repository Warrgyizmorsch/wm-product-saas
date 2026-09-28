<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Repositories\QuotationRepository;
use App\Domains\CRM\Services\QuotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class QuotationApiController extends Controller
{
    public function __construct(
        private readonly QuotationRepository $quotationRepo,
        private readonly QuotationService $quotationService,
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
     * GET /api/crm/quotations
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int)$request->input('per_page', 15), 100);
        $quotations = $this->quotationRepo->getPaginatedQuotations($request->all(), $perPage);

        return response()->json([
            'success' => true,
            'data'    => $quotations->items(),
            'meta'    => [
                'current_page' => $quotations->currentPage(),
                'last_page'    => $quotations->lastPage(),
                'per_page'     => $quotations->perPage(),
                'total'        => $quotations->total(),
            ],
        ]);
    }

    /**
     * GET /api/crm/quotations/{id}
     */
    public function show(int $id): JsonResponse
    {
        $quotation = Quotation::with(['lead', 'crmAccount', 'crmContact', 'deal', 'items.product'])->find($id);

        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $quotation,
        ]);
    }

    /**
     * POST /api/crm/quotations
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'quotation_number' => 'nullable|string|max:100',
            'customer_id'      => 'nullable|integer',
            'lead_id'          => 'nullable|integer',
            'crm_account_id'   => 'nullable|integer',
            'crm_deal_id'      => 'nullable|integer',
            'quotation_date'   => 'nullable|date',
            'valid_until'      => 'nullable|date',
            'subject'          => 'nullable|string|max:255',
            'terms'            => 'nullable|string',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.tax_rate'     => 'nullable|numeric|min:0',
            'items.*.discount_rate'=> 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['tenant_id']  = $tenantId;
        $validated['company_id'] = $companyId;
        $validated['branch_id']  = $branchId;
        $validated['user_id']    = auth()->id() ?? 1;

        if (empty($validated['quotation_number'])) {
            $validated['quotation_number'] = $this->quotationService->getNextQuotationNumber();
        }

        $quotation = $this->quotationService->create($validated, $validated['items']);

        return response()->json([
            'success' => true,
            'message' => 'Quotation created successfully.',
            'data'    => $quotation->load(['items.product', 'lead', 'crmAccount']),
        ], 201);
    }

    /**
     * POST /api/crm/quotations/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $oldStatus = $quotation->status;
        $this->quotationRepo->update($quotation, ['status' => 'Approved']);
        $this->quotationService->handleQuotationStatusChange($quotation, 'Approved', $quotation->lead_id);

        return response()->json([
            'success' => true,
            'message' => 'Quotation approved successfully.',
            'data'    => $quotation->fresh(['items.product', 'lead', 'crmAccount', 'deal']),
        ]);
    }

    /**
     * POST /api/crm/quotations/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $reason = $request->input('rejection_reason');
        $this->quotationRepo->update($quotation, [
            'status'           => 'Rejected',
            'rejection_reason' => $reason,
        ]);
        $this->quotationService->handleQuotationStatusChange($quotation, 'Rejected', $quotation->lead_id);

        return response()->json([
            'success' => true,
            'message' => 'Quotation rejected.',
            'data'    => $quotation->fresh(['items.product', 'lead', 'crmAccount', 'deal']),
        ]);
    }

    /**
     * POST /api/crm/leads/{leadId}/convert-to-quotation
     */
    public function convertFromLead(int $leadId): JsonResponse
    {
        $lead = Lead::find($leadId);
        if (!$lead) {
            return response()->json(['success' => false, 'message' => "Lead #{$leadId} not found."], 404);
        }

        $quotation = $this->quotationService->createFromLead($lead);

        return response()->json([
            'success' => true,
            'message' => 'Quotation generated from Lead successfully.',
            'data'    => $quotation->load(['items', 'lead']),
        ], 201);
    }

    /**
     * PUT/PATCH /api/crm/quotations/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'quotation_date'   => 'nullable|date',
            'valid_until'      => 'nullable|date',
            'terms'            => 'nullable|string',
            'notes'            => 'nullable|string',
            'status'           => 'nullable|string',
            'items'            => 'nullable|array|min:1',
            'items.*.product_id'   => 'required_with:items|integer',
            'items.*.quantity'     => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'   => 'required_with:items|numeric|min:0',
            'items.*.tax_rate'     => 'nullable|numeric|min:0',
            'items.*.discount_rate'=> 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['items'])) {
            $quotation = $this->quotationService->update($quotation, $validated, $validated['items']);
        } else {
            $quotation->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quotation updated successfully.',
            'data'    => $quotation->fresh(['items.product', 'lead', 'crmAccount']),
        ]);
    }

    /**
     * DELETE /api/crm/quotations/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $quotation->delete();

        return response()->json([
            'success' => true,
            'message' => "Quotation #{$id} deleted successfully.",
        ]);
    }
}
