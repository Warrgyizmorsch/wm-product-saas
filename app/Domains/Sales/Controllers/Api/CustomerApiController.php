<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\PaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\PartyDuplicateService;
use App\Services\TaxIdentityService;

class CustomerApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = company_id() ?? $user?->company_id;
        $branchId  = branch_id() ?? $user?->branch_id;

        return [(int)$tenantId, (int)$companyId, $branchId ? (int)$branchId : null];
    }

    /**
     * GET /api/sales/customers/meta
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'       => ['active', 'inactive', 'blocked'],
                'gst_types'      => ['Registered', 'Unregistered', 'Composition', 'Overseas', 'SEZ'],
                'payment_terms'  => ['Immediate', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Due on Receipt'],
            ],
        ]);
    }

    /**
     * GET /api/sales/customers
     * List customers with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = Customer::query()
            ->where('tenant_id', $tenantId)
            ->withCount(['salesOrders', 'invoices', 'payments']);

        $authUser = auth()->user();
        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'crm.customers.view', ['tenant_id' => $tenantId])) {
            $query->whereHas('crmAccount', fn($q) => $q->where('owner_id', $authUser->id));
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Has Outstanding Invoices
        if ($request->boolean('has_outstanding')) {
            $query->whereHas('invoices', function ($q) {
                $q->where('balance_due', '>', 0);
            });
        }

        // Sorting
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'name', 'company_name', 'email', 'phone', 'created_at', 'status', 'opening_balance'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage   = min((int)$request->input('per_page', 15), 100);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $customers->items(),
            'meta'    => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/customers
     * Create a new customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => ['required', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin'            => TaxIdentityService::gstinRules(false),
            'pan'              => TaxIdentityService::panRules(false),
            'bank_name'        => ['nullable', 'string', 'max:150'],
            'account_number'   => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code'        => ['nullable', 'string', 'max:30'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
        ], [
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'   => 'Phone number cannot exceed 20 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        [$tenantId, $companyId, $branchId] = $this->resolveTenantContext();
        $validated = $validator->validated();

        // FRS 2.3: possible duplicates are reported (409) until the caller re-sends with confirm_duplicate=true.
        $duplicates = app(PartyDuplicateService::class)->find(PartyDuplicateService::CUSTOMER, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return response()->json([
                'success' => false,
                'message' => 'Possible duplicate customer: ' . app(PartyDuplicateService::class)->summary($duplicates)
                    . '. Re-send with confirm_duplicate=true to create it anyway.',
                'duplicates' => $duplicates,
            ], 409);
        }

        $verification = app(\App\Services\PartyVerificationService::class);
        $customer = Customer::create(array_merge([
            'tenant_id'        => $tenantId,
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'name'             => $validated['name'],
            'company_name'     => $validated['company_name'] ?? null,
            'email'            => $validated['email'] ?? null,
            'phone'            => $validated['phone'] ?? null,
            'gstin'            => $validated['gstin'] ?? null,
            'pan'              => $validated['pan'] ?? null,
            'bank_name'        => $validated['bank_name'] ?? null,
            'account_number'   => $validated['account_number'] ?? null,
            'ifsc_code'        => $validated['ifsc_code'] ?? null,
            'status'           => $validated['status'] ?? 'active',
            'billing_address'  => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'opening_balance'  => $validated['opening_balance'] ?? 0.00,
            'opening_balance_type' => $validated['opening_balance_type'] ?? 'debit',
        ], $verification->pendingAttributes()));
        $verification->recordSubmission($customer);

        // Previously saved on the customer only, never posted to the ledger.
        $openingWarning = app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'warning' => $openingWarning,
            'data'    => $customer,
        ], 201);
    }

    /**
     * GET /api/sales/customers/{id}
     * View customer profile, active stats, and recent orders.
     */
    public function show(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $customer = Customer::where('tenant_id', $tenantId)
            ->with([
                'salesOrders' => fn($q) => $q->latest()->limit(5),
                'invoices'    => fn($q) => $q->latest()->limit(5),
                'payments'    => fn($q) => $q->latest()->limit(5),
            ])
            ->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $authUser = auth()->user();
        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'crm.customers.view', [
            'tenant_id' => $tenantId,
            'owner_id'  => $customer->crmAccount?->owner_id,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this customer.',
            ], 403);
        }

        $totalInvoiced = (float) Invoice::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('total_amount');
        $totalPaid     = (float) CustomerPayment::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('amount');
        $balanceDue    = (float) Invoice::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('balance_due');

        $postedPayments = CustomerPayment::where('tenant_id', $tenantId)
            ->where('customer_id', $id)
            ->whereIn('status', ['Posted', 'posted', 'completed', 'paid'])
            ->with('allocations')
            ->get();

        $unallocatedAdvanceAllocs = (float) PaymentAllocation::whereHas('payment', function ($q) use ($id, $tenantId) {
            $q->where('tenant_id', $tenantId)->where('customer_id', $id)->whereIn('status', ['Posted', 'posted', 'completed', 'paid']);
        })->whereNull('invoice_id')->sum('allocated_amount');

        $unallocatedPaymentsBal = (float) $postedPayments->sum(function ($p) {
            return max(0, (float) $p->amount - (float) $p->allocations->sum('allocated_amount'));
        });

        $availableAdvance = round($unallocatedAdvanceAllocs + $unallocatedPaymentsBal, 2);
        $netReceivables   = max(0, round($balanceDue - $availableAdvance, 2));

        return response()->json([
            'success' => true,
            'data'    => [
                'customer' => $customer,
                'stats'    => [
                    'total_invoiced'    => $totalInvoiced,
                    'total_paid'        => $totalPaid,
                    'balance_due'       => $balanceDue,
                    'available_advance' => $availableAdvance,
                    'net_receivables'   => $netReceivables,
                ],
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/sales/customers/{id}
     * Update customer details.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $customer   = Customer::where('tenant_id', $tenantId)->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $authUser = auth()->user();
        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'crm.customers.update', [
            'tenant_id' => $tenantId,
            'owner_id'  => $customer->crmAccount?->owner_id,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this customer.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin'            => TaxIdentityService::gstinRules(false),
            'pan'              => TaxIdentityService::panRules(false),
            'bank_name'        => ['nullable', 'string', 'max:150'],
            'account_number'   => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code'        => ['nullable', 'string', 'max:30'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
        ], [
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'   => 'Phone number cannot exceed 20 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // FRS 2.3: same rule as the web edit form: a changed name, GSTIN, PAN, email, mobile or address
        // that matches another customer is reported (409) until the caller re-sends with confirm_duplicate=true.
        $finder = app(PartyDuplicateService::class);
        $duplicates = $finder->findOnUpdate(PartyDuplicateService::CUSTOMER, $customer, $data, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return response()->json([
                'success' => false,
                'message' => 'Possible duplicate customer: ' . $finder->summary($duplicates)
                    . '. Re-send with confirm_duplicate=true to save it anyway.',
                'duplicates' => $duplicates,
            ], 409);
        }

        $warnings = [];
        $verification = app(\App\Services\PartyVerificationService::class);
        if (array_key_exists('status', $data) && $verification->enabled()) {
            $requestedStatus = strtolower((string) $data['status']);
            if ($requestedStatus === 'active' && ($blocked = $verification->activationBlockedMessage($customer))) {
                return response()->json(['success' => false, 'message' => $blocked], 422);
            }
            if ($customer->isVerified() && $requestedStatus !== strtolower((string) $customer->status)) {
                unset($data['status']);
                $warnings[] = 'Status of a verified customer must be changed through the deactivate or reactivate action.';
            }
        }

        $data = array_merge($data, $verification->attributesForUpdate($customer, $data));
        $customer->update($data);

        $openingWarning = app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer->fresh());
        if ($openingWarning) {
            $warnings[] = $openingWarning;
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'warning' => $warnings !== [] ? implode(' ', $warnings) : null,
            'data'    => $customer->fresh(),
        ]);
    }

    /**
     * DELETE /api/sales/customers/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $customer   = Customer::where('tenant_id', $tenantId)->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $authUser = auth()->user();
        if ($authUser && !app(\App\Services\Access\AccessService::class)->allows($authUser, 'crm.customers.delete', [
            'tenant_id' => $tenantId,
            'owner_id'  => $customer->crmAccount?->owner_id,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this customer.',
            ], 403);
        }

        // FRS 2.3: Parties linked to transactions are never hard-deleted.
        $guard = app(\App\Services\PartyDeletionGuard::class);
        if ($guard->hasTransactions($customer)) {
            $statusService = app(\App\Services\PartyStatusService::class);
            $user = auth()->user() ?? \App\Models\User::where('tenant_id', $tenantId)->first();
            if ($statusService->enabled() && $customer->isVerified() && $statusService->isActive($customer) && $user) {
                try {
                    $statusService->deactivate($customer, $user, 'business_closed', 'Customer has transaction records; deactivated upon deletion attempt.');
                } catch (\Throwable) {
                    $customer->update(['status' => 'inactive', 'inactive_reason' => 'business_closed']);
                }
            } else {
                $customer->update(['status' => 'inactive', 'inactive_reason' => 'business_closed']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer has transaction records; status changed to inactive.',
                'summary' => $guard->transactionSummary($customer),
            ]);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
