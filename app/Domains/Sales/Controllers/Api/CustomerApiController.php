<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerApiController extends Controller
{
    private function resolveTenantContext(): array
    {
        $user      = auth()->user();
        $tenantId  = $user?->tenant_id  ?? (tenant_id() ?? 1);
        $companyId = $user?->company_id ?? (company_id() ?? 1);
        $branchId  = $user?->branch_id  ?? (branch_id() ?? null);

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
            'phone'            => ['nullable', 'string', 'max:50'],
            'gstin'            => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
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

        $customer = Customer::create([
            'tenant_id'        => $tenantId,
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'name'             => $validated['name'],
            'company_name'     => $validated['company_name'] ?? null,
            'email'            => $validated['email'] ?? null,
            'phone'            => $validated['phone'] ?? null,
            'gstin'            => $validated['gstin'] ?? null,
            'status'           => $validated['status'] ?? 'active',
            'billing_address'  => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'opening_balance'  => $validated['opening_balance'] ?? 0.00,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
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

        $totalInvoiced = (float) Invoice::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('total_amount');
        $totalPaid     = (float) CustomerPayment::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('amount');
        $balanceDue    = (float) Invoice::where('tenant_id', $tenantId)->where('customer_id', $id)->sum('balance_due');

        return response()->json([
            'success' => true,
            'data'    => [
                'customer' => $customer,
                'stats'    => [
                    'total_invoiced' => $totalInvoiced,
                    'total_paid'     => $totalPaid,
                    'balance_due'    => $balanceDue,
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

        $validator = Validator::make($request->all(), [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'gstin'            => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $customer->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
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

        // Check active orders or pending balances
        $hasInvoices = Invoice::where('tenant_id', $tenantId)->where('customer_id', $id)->exists();
        if ($hasInvoices) {
            $customer->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Customer has transaction records; status changed to inactive.',
            ]);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
