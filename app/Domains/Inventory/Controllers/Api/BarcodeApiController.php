<?php

namespace App\Domains\Inventory\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\SerialNumber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class BarcodeApiController extends Controller
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
     * GET /api/inventory/barcodes/meta
     */
    public function meta(): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();
        $products   = Product::where('tenant_id', $tenantId)->sellable()->orderBy('name')->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'track_serial_number']);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data'    => [
                'products'   => $products,
                'warehouses' => $warehouses,
            ],
        ]);
    }

    /**
     * GET /api/inventory/barcodes/serials/{productId}
     */
    public function getSerials(int $productId): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $serials = SerialNumber::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', 'Available')
            ->pluck('serial_number');

        return response()->json([
            'success' => true,
            'data'    => $serials,
        ]);
    }

    /**
     * POST /api/inventory/barcodes/print
     */
    public function print(Request $request): JsonResponse
    {
        [$tenantId] = $this->resolveTenantContext();

        $validator = Validator::make($request->all(), [
            'product_id'     => ['required', 'integer'],
            'print_type'     => ['nullable', 'string', 'in:product,serial'],
            'warehouse_id'   => ['nullable', 'integer'],
            'copies'         => ['nullable', 'integer', 'min:1', 'max:500'],
            'serial_numbers' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $product = Product::where('tenant_id', $tenantId)->find($request->input('product_id'));
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }
        $printType = $request->input('print_type', 'product');
        $labels    = [];
        $whSuffix  = $request->filled('warehouse_id') ? '@' . $request->input('warehouse_id') : '';

        if ($printType === 'serial' && $request->filled('serial_numbers')) {
            $rawSerials = $request->input('serial_numbers');
            $selectedSerials = is_array($rawSerials) ? $rawSerials : explode(',', (string)$rawSerials);

            foreach ($selectedSerials as $sn) {
                $snClean = trim($sn);
                if (!empty($snClean)) {
                    $labels[] = [
                        'product_name'  => $product->name,
                        'sku'           => $product->sku,
                        'price'         => (float)$product->selling_price,
                        'barcode_value' => $snClean . $whSuffix,
                        'serial_number' => $snClean,
                        'is_serial'     => true,
                    ];
                }
            }
        }

        if (empty($labels)) {
            $copies       = max(1, (int)$request->input('copies', 1));
            $barcodeValue = ($product->barcode ?: $product->sku) . $whSuffix;
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = [
                    'product_name'  => $product->name,
                    'sku'           => $product->sku,
                    'price'         => (float)$product->selling_price,
                    'barcode_value' => $barcodeValue,
                    'serial_number' => null,
                    'is_serial'     => false,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'product'    => $product,
                'print_type' => $printType,
                'count'      => count($labels),
                'labels'     => $labels,
            ],
        ]);
    }
}
