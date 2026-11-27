<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\SerialNumber;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id;
        if (!$tenantId) abort(403, 'Tenant context missing.');
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.barcodes.index', compact('products', 'warehouses'));
    }

    public function getSerials(Request $request, Product $product)
    {
        $this->authorize('view', $product);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id;
        if (!$tenantId) abort(403, 'Tenant context missing.');
        $serials = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('status', 'Available')
            ->pluck('serial_number');

        return response()->json(['success' => true, 'serials' => $serials]);
    }

    public function print(Request $request)
    {
        $this->authorize('viewAny', Product::class);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id;
        if (!$tenantId) abort(403, 'Tenant context missing.');

        $request->validate([
            'product_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('products', 'id')->where(fn($q) => $q->where('tenant_id', $tenantId)),
            ],
            'print_type' => 'nullable|string|in:product,serial',
            'copies'     => 'nullable|integer|min:1|max:500',
        ]);

        $product = Product::where('tenant_id', $tenantId)->findOrFail($request->product_id);
        $printType = $request->input('print_type', 'product');

        $labels = [];

        $whSuffix = $request->filled('warehouse_id') ? '@' . $request->warehouse_id : '';

        if ($printType === 'serial' && $request->filled('serial_numbers')) {
            $selectedSerials = is_array($request->serial_numbers) 
                ? $request->serial_numbers 
                : explode(',', $request->serial_numbers);

            foreach ($selectedSerials as $sn) {
                $snClean = trim($sn);
                if (!empty($snClean)) {
                    $labels[] = [
                        'product_name' => $product->name,
                        'sku'          => $product->sku,
                        'price'        => $product->selling_price,
                        'barcode_value'=> $snClean . $whSuffix,
                        'serial_number'=> $snClean,
                        'is_serial'    => true,
                    ];
                }
            }
        }

        if (empty($labels)) {
            $copies = min(500, max(1, (int)$request->input('copies', 1)));
            $barcodeValue = ($product->barcode ?: $product->sku) . $whSuffix;
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'product_name' => $product->name,
                    'sku'          => $product->sku,
                    'price'        => $product->selling_price,
                    'barcode_value'=> $barcodeValue,
                    'serial_number'=> null,
                    'is_serial'    => false,
                ];
            }
        }

        return view('modules.inventory.barcodes.print', compact('product', 'labels', 'printType'));
    }
}
