<?php

namespace App\Domains\Purchase\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VendorApiController extends Controller
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
     * GET /api/purchase/vendors/meta
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'statuses'      => ['active', 'inactive', 'blocked'],
                'gst_types'     => ['Registered', 'Unregistered', 'Composition', 'Overseas', 'SEZ'],
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
            ->withCount(['purchaseOrders', 'bills']);

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
        $sortBy  = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'name', 'company_name', 'email', 'phone', 'created_at', 'status', 'opening_balance'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int)$request->input('per_page', 15), 100);
        $vendors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $vendors->items(),
            'meta'    => [
                'current_page' => $vendors->currentPage(),
                'last_page'    => $vendors->lastPage(),
                'per_page'     => $vendors->perPage(),
                'total'        => $vendors->total(),
            ],
        ]);
    }

    /**
     * POST /api/purchase/vendors
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => ['required', 'string', 'max:255'],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'gstin'            => ['nullable', 'string', 'max:50'],
            'pan'              => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
            'payment_terms'    => ['nullable', 'string'],
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

        $vendor = Vendor::create([
            'tenant_id'        => $tenantId,
            'company_id'       => $companyId,
            'branch_id'        => $branchId,
            'name'             => $validated['name'],
            'company_name'     => $validated['company_name'] ?? null,
            'email'            => $validated['email'] ?? null,
            'phone'            => $validated['phone'] ?? null,
            'gstin'            => $validated['gstin'] ?? null,
            'pan'              => $validated['pan'] ?? null,
            'status'           => $validated['status'] ?? 'active',
            'billing_address'  => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
            'opening_balance'  => $validated['opening_balance'] ?? 0.00,
            'payment_terms'    => $validated['payment_terms'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor created successfully',
            'data'    => $vendor,
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
                'bills'          => fn($q) => $q->latest()->limit(5),
            ])
            ->findOrFail($id);

        $totalPurchased = (float) PurchaseOrder::where('tenant_id', $tenantId)->where('vendor_id', $id)->sum('grand_total');
        $totalBilled    = (float) VendorBill::where('tenant_id', $tenantId)->where('vendor_id', $id)->sum('grand_total');
        $totalPaid      = (float) VendorPayment::where('tenant_id', $tenantId)->where('vendor_id', $id)->sum('amount');
        $balancePayable = max(0, $totalBilled - $totalPaid);

        return response()->json([
            'success' => true,
            'data'    => [
                'vendor' => $vendor,
                'stats'  => [
                    'total_purchased' => $totalPurchased,
                    'total_billed'    => $totalBilled,
                    'total_paid'      => $totalPaid,
                    'balance_payable' => $balancePayable,
                ],
            ],
        ]);
    }

    /**
     * PUT/PATCH /api/purchase/vendors/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendor     = Vendor::where('tenant_id', $tenantId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'             => ['sometimes', 'required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('vendors')->where('tenant_id', $tenantId)->ignore($vendor->id)],
            'company_name'     => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'gstin'            => ['nullable', 'string', 'max:50'],
            'pan'              => ['nullable', 'string', 'max:50'],
            'status'           => ['nullable', 'in:active,inactive,blocked'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance'  => ['nullable', 'numeric'],
            'payment_terms'    => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $vendor->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully',
            'data'    => $vendor->fresh(),
        ]);
    }

    /**
     * DELETE /api/purchase/vendors/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendor     = Vendor::where('tenant_id', $tenantId)->findOrFail($id);

        $hasOrders = PurchaseOrder::where('tenant_id', $tenantId)->where('vendor_id', $id)->exists();
        if ($hasOrders) {
            $vendor->update(['status' => 'inactive']);
            return response()->json([
                'success' => true,
                'message' => 'Vendor has order records; status set to inactive.',
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

        $validator = Validator::make($request->all(), [
            'name'         => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:255'],
            'gstin'        => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $vendor = Vendor::create([
            'tenant_id'    => $tenantId,
            'company_id'   => $companyId,
            'branch_id'    => $branchId,
            'name'         => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'phone'        => $validated['phone'] ?? null,
            'email'        => $validated['email'] ?? null,
            'gstin'        => $validated['gstin'] ?? null,
            'status'       => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor quick-created successfully',
            'data'    => $vendor,
        ], 201);
    }

    /**
     * POST /api/purchase/vendors/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $vendor     = Vendor::where('tenant_id', $tenantId)->findOrFail($id);

        $vendor->status = $vendor->status === 'active' ? 'inactive' : 'active';
        $vendor->save();

        return response()->json([
            'success' => true,
            'message' => "Vendor status toggled to {$vendor->status}",
            'data'    => $vendor,
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
}
