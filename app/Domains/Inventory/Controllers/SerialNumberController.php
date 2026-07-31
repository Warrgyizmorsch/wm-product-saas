<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SerialNumberController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();

        $query = SerialNumber::query()
            ->where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'batch', 'transactionIn', 'transactionOut']);

        // Filter by Product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Search by Serial Number or Product SKU/Name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $serials = $query->latest()->paginate(25)->withQueryString();

        $products = Product::where('tenant_id', $tenantId)
            ->where('track_serial_number', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('modules.inventory.serial-numbers.index', compact('serials', 'products', 'warehouses'));
    }
}
