<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = require_tenant_id();

        $query = Batch::query()
            ->where('tenant_id', $tenantId)
            ->with(['product', 'warehouse']);

        // Filter by Product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by Warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by Expiry Status
        if ($request->filled('expiry_filter')) {
            $filter = $request->expiry_filter;
            if ($filter === 'expired') {
                $query->where('expiry_date', '<', now());
            } elseif ($filter === 'expiring_soon') {
                $query->whereBetween('expiry_date', [now(), now()->addDays(30)]);
            } elseif ($filter === 'fresh') {
                $query->where('expiry_date', '>', now()->addDays(30));
            }
        }

        // Search by Batch Number or Product SKU/Name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'expiry_date');
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (in_array($sortBy, ['expiry_date', 'batch_number', 'available_qty', 'quantity', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('expiry_date', 'asc');
        }

        $batches = $query->paginate(10)->withQueryString();

        $products = Product::where('tenant_id', $tenantId)
            ->where('track_batch', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('modules.inventory.batches.index', compact('batches', 'products', 'warehouses'));
    }
}
