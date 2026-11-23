<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Services\PartyBankChangeService;
use App\Services\PartyDuplicateService;
use App\Services\PartyStatusException;
use App\Services\PartyStatusService;
use App\Services\PartyVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?? (tenant_id() ?? 1);
        $companyId = company_id() ?? $user?->company_id;
        $branchId = branch_id() ?? $user?->branch_id;

        return [(int) $tenantId, (int) $companyId, $branchId ? (int) $branchId : null];
    }

    /**
     * GET /api/purchase/vendors/meta
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => ['active', 'inactive', 'blocked'],
                'gst_types' => ['Registered', 'Unregistered', 'Composition', 'Overseas', 'SEZ'],
                'payment_terms' => ['Immediate', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt'],
            ],
        ]);
    }

    /**
     * GET /api/purchase/vendors
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Vendor::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['purchaseOrders', 'bills', 'advancePayments']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'name', 'company_name', 'email', 'phone', 'created_at', 'status', 'opening_balance'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $vendors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/vendors
     */
    public function store(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $uniqueScope = $this->scopeUniqueVendor($tenantId, $companyId, $branchId);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('vendors', 'name')->where($uniqueScope)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('vendors', 'email')->where($uniqueScope)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'pan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'payment_terms' => ['nullable', 'string'],
        ], [
            'email.email' => 'The email field must be a valid email address.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        // FRS 2.3: possible duplicates are reported (409) until the caller re-sends with confirm_duplicate=true.
        $duplicates = app(PartyDuplicateService::class)->find(PartyDuplicateService::VENDOR, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return $this->duplicateResponse($duplicates);
        }

        $status = $validated['status'] ?? 'active';
        $inactiveReason = null;
        if (strtolower((string) $status) === 'blocked') {
            $status = 'inactive';
            $inactiveReason = 'compliance_issue';
        }

        $verification = app(PartyVerificationService::class);
        $vendor = Vendor::create(array_merge([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'pan' => $validated['pan'] ?? null,
            'status' => $status,
            'inactive_reason' => $inactiveReason,
            'address' => $validated['address'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'ifsc_code' => $validated['ifsc_code'] ?? null,
            'opening_balance' => isset($validated['opening_balance']) ? convert_to_base((float) $validated['opening_balance']) : 0.00,
            'opening_balance_type' => $validated['opening_balance_type'] ?? 'credit',
            'payment_terms' => $validated['payment_terms'] ?? null,
        ], $verification->pendingAttributes()));
        $verification->recordSubmission($vendor);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);

        return response()->json([
            'success' => true,
            'message' => 'Vendor created successfully',
            'warning' => $openingWarning,
            'data' => $vendor,
        ], 201);
    }

    /**
     * GET /api/purchase/vendors/{id}
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $vendor = Vendor::where('tenant_id', $tenantId)
            ->with([
                'purchaseOrders' => fn($q) => $q->latest()->limit(5),
                'bills' => fn($q) => $q->latest()->limit(5),
                'payments' => fn($q) => $q->latest()->limit(5),
                'advancePayments' => fn($q) => $q->with('purchaseOrder')->latest()->limit(5),
            ])
            ->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $bills = VendorBill::where('tenant_id', $tenantId)->where('vendor_id', $id)->get();
        $payments = VendorPayment::where('tenant_id', $tenantId)->where('vendor_id', $id)->get();
        $advances = \App\Domains\Purchase\Models\PurchaseAdvancePayment::where('tenant_id', $tenantId)->where('vendor_id', $id)->get();

        $totalPurchased = (float) PurchaseOrder::where('tenant_id', $tenantId)->where('vendor_id', $id)->sum('grand_total');
        $totalBilled = (float) $bills->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalPaid = (float) $payments
            ->reject(fn ($p) => in_array(strtolower((string) $p->status), ['cancelled', 'void', 'draft', 'rejected'], true))
            ->sum('amount');
        $totalAdvancePaid = (float) $advances->where('status', '!=', 'cancelled')->sum('amount');
        $availableAdvance = app(\App\Domains\Purchase\Services\VendorBillService::class)->getAvailableVendorAdvance($id, $tenantId);

        $openingBalance = $vendor->signedOpeningBalance();
        $netOutstanding = $openingBalance + $totalBilled - $totalPaid;
        $balancePayable = max(0, $netOutstanding);
        $vendorAdvanceCredit = max($availableAdvance, $netOutstanding < 0 ? abs($netOutstanding) : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'vendor' => $vendor,
                'stats' => [
                    'total_purchased' => round($totalPurchased, 2),
                    'total_billed' => round($totalBilled, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_advance_paid' => round($totalAdvancePaid, 2),
                    'available_advance' => round($availableAdvance, 2),
                    'vendor_advance_credit' => round($vendorAdvanceCredit, 2),
                    'net_outstanding' => round($netOutstanding, 2),
                    'balance_payable' => round($balancePayable, 2),
                ],
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/purchase/vendors/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $vendor = Vendor::where('tenant_id', $tenantId)->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $targetCompanyId = $vendor->company_id ?? $companyId;
        $targetBranchId = $vendor->branch_id ?? $branchId;
        $uniqueScope = $this->scopeUniqueVendor($tenantId, $targetCompanyId, $targetBranchId);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('vendors', 'name')->ignore($vendor->id)->where($uniqueScope)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('vendors', 'email')->ignore($vendor->id)->where($uniqueScope)],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:50'],
            'pan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'payment_terms' => ['nullable', 'string'],
        ], [
            'email.email' => 'The email field must be a valid email address.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // FRS 2.3: same rule as the web edit form: a changed name, GSTIN, PAN, email, mobile or address
        // that matches another vendor is reported (409) until the caller re-sends with confirm_duplicate=true.
        $duplicates = app(PartyDuplicateService::class)->findOnUpdate(PartyDuplicateService::VENDOR, $vendor, $data, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return $this->duplicateResponse($duplicates, 'save it');
        }

        if (array_key_exists('opening_balance', $data)) {
            $data['opening_balance'] = $data['opening_balance'] !== null
                ? convert_to_base((float) $data['opening_balance'])
                : 0.00;
        }

        $warnings = [];
        [$data, $bankAttempted] = app(PartyBankChangeService::class)->stripProtectedFields($vendor, $data);
        if ($bankAttempted !== []) {
            $warnings[] = 'Bank details are locked and were not changed. Raise a callback-verified bank change request instead.';
        }

        $verification = app(PartyVerificationService::class);
        if (array_key_exists('status', $data) && $verification->enabled()) {
            $requestedStatus = strtolower((string) $data['status']);
            if ($requestedStatus === 'active' && ($blocked = $verification->activationBlockedMessage($vendor))) {
                return response()->json(['success' => false, 'message' => $blocked], 422);
            }
            if ($vendor->isVerified() && $requestedStatus !== strtolower((string) $vendor->status)) {
                unset($data['status']);
                $warnings[] = 'Status of a verified vendor must be changed through the deactivate or reactivate action.';
            }
        }

        $data = array_merge($data, $verification->attributesForUpdate($vendor, $data));
        $vendor->update($data);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);
        if ($openingWarning) {
            $warnings[] = $openingWarning;
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully',
            'warning' => $warnings !== [] ? implode(' ', $warnings) : null,
            'data' => $vendor->fresh(),
        ]);
    }

    /**
     * DELETE /api/purchase/vendors/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendor = Vendor::where('tenant_id', $tenantId)->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        // FRS 2.3: Parties linked to transactions are never hard-deleted.
        $guard = app(\App\Services\PartyDeletionGuard::class);
        if ($guard->hasTransactions($vendor)) {
            $statusService = app(\App\Services\PartyStatusService::class);
            $user = auth()->user() ?? \App\Models\User::where('tenant_id', $tenantId)->first();
            if ($statusService->enabled() && $vendor->isVerified() && $statusService->isActive($vendor) && $user) {
                try {
                    $statusService->deactivate($vendor, $user, 'business_closed', 'Vendor has transaction records; deactivated upon deletion attempt.');
                } catch (\Throwable) {
                    $vendor->update(['status' => 'inactive', 'inactive_reason' => 'business_closed']);
                }
            } else {
                $vendor->update(['status' => 'inactive', 'inactive_reason' => 'business_closed']);
            }
            return response()->json([
                'success' => true,
                'message' => 'Vendor has order records; status set to inactive.',
                'summary' => $guard->transactionSummary($vendor),
            ]);
        }

        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor deleted successfully',
        ]);
    }

    /**
     * POST /api/purchase/vendors/quick-create
     */
    public function quickCreate(Request $request): JsonResponse
    {
        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $uniqueScope = $this->scopeUniqueVendor($tenantId, $companyId, $branchId);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('vendors', 'name')->where($uniqueScope)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('vendors', 'email')->where($uniqueScope)],
            'gstin' => ['nullable', 'string', 'max:50'],
        ], [
            'email.email' => 'The email field must be a valid email address.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // FRS 2.3: quick-create only stops on the same GSTIN, email or mobile (not on a similar name).
        $finder = app(PartyDuplicateService::class);
        $identityMatches = $finder->identityMatches($finder->find(PartyDuplicateService::VENDOR, $validated, $tenantId));
        if ($identityMatches !== [] && ! $request->boolean('confirm_duplicate')) {
            return $this->duplicateResponse($identityMatches);
        }

        $verification = app(PartyVerificationService::class);
        $vendor = Vendor::create(array_merge([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'status' => 'active',
        ], $verification->pendingAttributes()));
        $verification->recordSubmission($vendor);

        return response()->json([
            'success' => true,
            'message' => 'Vendor quick-created successfully',
            'data' => $vendor,
        ], 201);
    }

    private function duplicateResponse(array $duplicates, string $action = 'create it'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Possible duplicate vendor: ' . app(PartyDuplicateService::class)->summary($duplicates)
                . ". Re-send with confirm_duplicate=true to {$action} anyway.",
            'duplicates' => $duplicates,
        ], 409);
    }

    /**
     * POST /api/purchase/vendors/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendor = Vendor::where('tenant_id', $tenantId)->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $target = strtolower((string) $vendor->status) === 'active' ? 'inactive' : 'active';

        if ($target === 'active' && ($blocked = app(PartyVerificationService::class)->activationBlockedMessage($vendor))) {
            return response()->json([
                'success' => false,
                'message' => $blocked,
            ], 422);
        }

        $statusService = app(PartyStatusService::class);
        if ($target === 'active' && $statusService->enabled() && ! $statusService->isActive($vendor)) {
            try {
                $statusService->reactivate($vendor, auth()->user());
            } catch (PartyStatusException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        } else {
            $vendor->status = $target;
            $vendor->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Vendor status toggled to {$vendor->status}",
            'data' => $vendor->fresh(),
        ]);
    }

    /**
     * GET /api/purchase/vendors/export
     */
    public function export(Request $request)
    {
        [$tenantId] = $this->resolveTenantContext();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\VendorExport($tenantId, $request->all()),
            'vendors_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    private function scopeUniqueVendor(int $tenantId, ?int $companyId, ?int $branchId): \Closure
    {
        return function ($query) use ($tenantId, $companyId, $branchId) {
            $query->where('tenant_id', $tenantId);
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereNull('company_id');
            }
            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            } else {
                $query->whereNull('branch_id');
            }
            return $query;
        };
    }
}
