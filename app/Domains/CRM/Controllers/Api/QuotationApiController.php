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
        $companyId = company_id() ?? $user?->company_id;
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
        $quotation = Quotation::with(['lead', 'crmAccount', 'deal', 'items.product'])->find($id);

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

    /**
     * POST /api/crm/quotations/{id}/send-email
     */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'to_email'   => 'required|email',
            'subject'    => 'required|string|max:255',
            'body_html'  => 'required|string',
            'account_id' => 'nullable|exists:email_configurations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tenantSettings = is_array(tenant()?->settings) ? tenant()->settings : [];
        $approvalPolicy = $tenantSettings['quotation_approval_policy'] ?? 'approval_required';
        if ($approvalPolicy !== 'auto_approve' && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Sent', 'Accepted'])) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation must be Approved before sending to client.',
            ], 422);
        }

        try {
            /** @var \App\Services\EmailService $emailService */
            $emailService = app(\App\Services\EmailService::class);
            $emailService->sendQuotationEmail($quotation, $request->all());

            // Synchronize status to 'Quotation Sent' (same action as Mark as Sent)
            $oldStatus = $quotation->status;
            if (in_array($oldStatus, ['Draft', 'Approved', 'Pending Approval'])) {
                $quotation->update(['status' => 'Quotation Sent']);
                $leadId = $quotation->lead_id ?: ($quotation->crmDeal?->lead_id);
                if ($leadId && ($lead = Lead::find($leadId))) {
                    \App\Domains\CRM\Models\LeadHistory::logEvent(
                        $lead, 'quotation_status_changed', $oldStatus, 'Quotation Sent',
                        "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Quotation Sent' via Email dispatch"
                    );
                }
                try {
                    app(\App\Domains\CRM\Services\QuotationService::class)->handleQuotationStatusChange($quotation, 'Quotation Sent', $leadId);
                } catch (\Throwable $e) {}
            }

            return response()->json([
                'success' => true,
                'message' => "Quotation {$quotation->quotation_number} sent successfully to {$request->input('to_email')} with PDF attached!",
                'new_status' => 'Quotation Sent',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send Quotation Email: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/crm/quotations/{id}/send-whatsapp
     */
    public function sendWhatsApp(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $tenantSettings = is_array(tenant()?->settings) ? tenant()->settings : [];
        $approvalPolicy = $tenantSettings['quotation_approval_policy'] ?? 'approval_required';
        if ($approvalPolicy !== 'auto_approve' && !in_array($quotation->status, ['Approved', 'Quotation Sent', 'Sent', 'Accepted'])) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation must be Approved before sending to client.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'phone'      => 'required|string',
            'caption'    => 'nullable|string',
            'custom_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $phone = $request->input('phone');
        if (!empty($phone)) {
            $cleanPhone = preg_replace('/\D/', '', trim($phone));
            if (strlen($cleanPhone) === 10) {
                $cleanPhone = '91' . $cleanPhone;
            }
            $quotation->update(['phone' => $cleanPhone ?: $phone]);
        }

        try {
            /** @var \App\Services\WhatsAppService $waService */
            $waService = app(\App\Services\WhatsAppService::class);
            $result = $waService->sendQuotation(
                quotation: $quotation,
                mobile: $phone,
                customCaption: $request->input('caption'),
                customPdfFile: $request->file('custom_pdf')
            );

            if ($result['success']) {
                // Synchronize status to 'Quotation Sent' (same action as Mark as Sent)
                $oldStatus = $quotation->status;
                if (in_array($oldStatus, ['Draft', 'Approved', 'Pending Approval'])) {
                    $quotation->update(['status' => 'Quotation Sent']);
                    $leadId = $quotation->lead_id ?: ($quotation->crmDeal?->lead_id);
                    if ($leadId && ($lead = Lead::find($leadId))) {
                        \App\Domains\CRM\Models\LeadHistory::logEvent(
                            $lead, 'quotation_status_changed', $oldStatus, 'Quotation Sent',
                            "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to 'Quotation Sent' via WhatsApp dispatch"
                        );
                    }
                    try {
                        app(\App\Domains\CRM\Services\QuotationService::class)->handleQuotationStatusChange($quotation, 'Quotation Sent', $leadId);
                    } catch (\Throwable $e) {}
                }

                return response()->json([
                    'success' => true,
                    'message' => "Quotation {$quotation->quotation_number} sent via WhatsApp to {$phone}!",
                    'data'    => $result,
                    'new_status' => 'Quotation Sent',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send WhatsApp message.',
                    'status'  => $result['status'] ?? 'error',
                ], 422);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send WhatsApp quotation: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/crm/quotations/{id}/download
     */
    public function downloadPdf(int $id)
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.crm.quotations.pdf', compact('quotation'));
        $pdf->setOption('isPhpEnabled', true);
        return $pdf->download("Quotation_{$quotation->quotation_number}.pdf");
    }

    /**
     * PATCH /api/crm/quotations/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'status'           => ['required', 'string', 'in:Draft,Pending Approval,Approved,Sent,Quotation Sent,Accepted,Rejected,Quotation Rework,Converted,Won'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }

        $newStatus = $request->input('status');
        $oldStatus = $quotation->status;
        $updateFields = ['status' => $newStatus];
        if ($request->filled('rejection_reason')) {
            $updateFields['rejection_reason'] = $request->input('rejection_reason');
        }

        $this->quotationRepo->update($quotation, $updateFields);

        if ($quotation->lead_id && $oldStatus !== $newStatus && ($lead = \App\Domains\CRM\Models\Lead::find($quotation->lead_id))) {
            $logMsg = "Quotation {$quotation->quotation_number} status changed from '{$oldStatus}' to '{$newStatus}'";
            if (!empty($updateFields['rejection_reason'])) {
                $logMsg .= " (Reason: {$updateFields['rejection_reason']})";
            }
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $lead, 'quotation_status_changed', $oldStatus, $newStatus,
                $logMsg
            );
        }

        $this->quotationService->handleQuotationStatusChange($quotation, $newStatus, $quotation->lead_id);

        return response()->json([
            'success' => true,
            'message' => "Quotation status updated to {$newStatus} successfully.",
            'data'    => $quotation->fresh(['items.product', 'lead', 'crmAccount', 'deal']),
        ]);
    }

    /**
     * POST /api/crm/quotations/{id}/convert
     * Accept quotation and convert to Customer (or attach to existing Customer).
     */
    public function convert(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::with(['lead', 'items'])->find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            'conversion_mode'      => 'nullable|string|in:existing,create_new',
            'existing_customer_id' => 'required_if:conversion_mode,existing|nullable|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }

        [$tenantId] = $this->resolveTenantContext();
        $mode = $request->input('conversion_mode', 'create_new');

        if ($mode === 'existing') {
            $customer = \App\Domains\CRM\Models\Customer::find($request->input('existing_customer_id'));
            $account = \App\Domains\CRM\Models\CrmAccount::where('tenant_id', $tenantId)->where('customer_id', $customer->id)->first();
            if (!$account) {
                $account = \App\Domains\CRM\Models\CrmAccount::create([
                    'tenant_id'   => $tenantId,
                    'customer_id' => $customer->id,
                    'name'        => $customer->name,
                    'email'       => $customer->email,
                    'phone'       => $customer->phone,
                    'gstin'       => $customer->gstin,
                    'status'      => 'active',
                    'owner_id'    => auth()->id() ?: 1,
                ]);
            }

            $quotation->update([
                'status'         => 'Accepted',
                'crm_account_id' => $account->id,
            ]);

            $this->quotationService->handleQuotationStatusChange($quotation, 'Accepted', $quotation->lead_id);

            return response()->json([
                'success' => true,
                'message' => "Quotation #{$quotation->quotation_number} accepted and attached to existing customer '{$customer->name}'.",
                'data'    => [
                    'quotation' => $quotation->fresh(['items.product', 'lead', 'crmAccount', 'deal']),
                    'customer'  => $customer,
                    'account'   => $account,
                ],
            ]);
        } else {
            $quotation->update(['status' => 'Accepted']);
            $this->quotationService->handleQuotationStatusChange($quotation, 'Accepted', $quotation->lead_id);

            return response()->json([
                'success' => true,
                'message' => "Quotation #{$quotation->quotation_number} accepted and converted to new Customer.",
                'data'    => $quotation->fresh(['items.product', 'lead', 'crmAccount', 'deal']),
            ]);
        }
    }

    /**
     * POST /api/crm/quotations/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::with('items')->find($id);
        if (!$quotation) {
            return response()->json(['success' => false, 'message' => "Quotation #{$id} not found."], 404);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();

        $newQuotationNo = $this->quotationService->getNextQuotationNumber();
        $copy = $quotation->replicate();
        $copy->quotation_number = $newQuotationNo;
        $copy->status = 'Draft';
        $copy->quotation_date = now()->toDateString();
        $copy->expiry_date = now()->addDays(30)->toDateString();
        $copy->save();

        foreach ($quotation->items as $item) {
            $itemCopy = $item->replicate();
            $itemCopy->quotation_id = $copy->id;
            $itemCopy->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Quotation duplicated successfully as {$copy->quotation_number}.",
            'data'    => $copy->load(['items.product', 'lead', 'crmAccount']),
        ], 201);
    }
}
