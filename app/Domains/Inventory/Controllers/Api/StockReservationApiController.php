<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StockReservationApiController extends Controller
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
     * GET /api/inventory/reservations
     */
    public function index(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $query = StockReservation::query()
            ->with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($warehouseId = $request->input('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $perPage      = min((int)$request->input('per_page', 15), 100);
        $reservations = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $reservations->items(),
            'meta'    => [
                'current_page' => $reservations->currentPage(),
                'last_page'    => $reservations->lastPage(),
                'per_page'     => $reservations->perPage(),
                'total'        => $reservations->total(),
            ],
        ]);
    }

    /**
     * POST /api/inventory/reservations/{id}/release
     */
    public function release(int $id): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $reservation = StockReservation::where('tenant_id', $tenantId)->find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Stock Reservation not found',
            ], 404);
        }

        if ($reservation->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'Only Active reservations can be released.',
            ], 422);
        }

        try {
            StockService::releaseStock(
                tenantId: $tenantId,
                productId: $reservation->product_id,
                warehouseId: $reservation->warehouse_id,
                qty: (float)$reservation->reserved_qty,
                referenceType: $reservation->reference_type,
                referenceId: $reservation->reference_id,
                referenceItemId: $reservation->reference_item_id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to release reservation: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock reservation released successfully.',
            'data'    => $reservation->fresh(),
        ]);
    }
}
