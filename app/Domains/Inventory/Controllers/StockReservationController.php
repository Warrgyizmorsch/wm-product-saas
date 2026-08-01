<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\Request;

class StockReservationController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = StockReservation::query()
            ->with(['product', 'warehouse'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'reserved_qty', 'expires_at'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $reservations = $query->paginate(15);

        return view('modules.inventory.reservations.index', compact('reservations'));
    }

    public function release(StockReservation $reservation)
    {
        if ($reservation->status !== 'Active') {
            return back()->with('error', 'Only Active reservations can be released.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        StockService::releaseStock(
            tenantId: $tenantId,
            productId: $reservation->product_id,
            warehouseId: $reservation->warehouse_id,
            qty: (float)$reservation->reserved_qty,
            referenceType: $reservation->reference_type,
            referenceId: $reservation->reference_id,
            referenceItemId: $reservation->reference_item_id
        );

        return back()->with('success', 'Stock Reservation released successfully.');
    }
}
