<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();

        return view('modules.inventory.barcodes.index', compact('products'));
    }

    public function getSerials(Request $request, Product $product)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $serials = \App\Domains\Inventory\Models\SerialNumber::where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('status', 'Available')
            ->pluck('serial_number');

        return response()->json(['success' => true, 'serials' => $serials]);
    }

    public function print(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'print_type' => 'nullable|string|in:product,serial',
        ]);

        $product = Product::where('tenant_id', $tenantId)->findOrFail($request->product_id);
        $printType = $request->input('print_type', 'product');

        $labels = [];

        if ($printType === 'serial' && $request->filled('serial_numbers')) {
            $selectedSerials = is_array($request->serial_numbers) 
                ? $request->serial_numbers 
                : explode(',', $request->serial_numbers);

            foreach ($selectedSerials as $sn) {
                $snClean = trim($sn);
                if (!empty($snClean)) {
                    $labels[] = [
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->selling_price,
                        'barcode_value' => $snClean,
                        'serial_number' => $snClean,
                        'is_serial' => true,
                    ];
                }
            }
        }

        if (empty($labels)) {
            $copies = max(1, (int)$request->input('copies', 1));
            $barcodeValue = $product->barcode ?: $product->sku;
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->selling_price,
                    'barcode_value' => $barcodeValue,
                    'serial_number' => null,
                    'is_serial' => false,
                ];
            }
        }

        return view('modules.inventory.barcodes.print', compact('product', 'labels', 'printType'));
    }
}
