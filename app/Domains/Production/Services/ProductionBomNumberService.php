<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;

class ProductionBomNumberService
{
    /**
     * Generate the next automated BOM number for the tenant.
     * Uses Product SKU if available (e.g. BOM-PR-FG-RAD-750), or sequential number (e.g. BOM-000001).
     */
    public function generateNextNumber(int $tenantId, ?string $prefix = null, ?int $productId = null): string
    {
        if ($productId) {
            $product = \App\Domains\Inventory\Models\Product::find($productId);
            if ($product && !empty($product->sku)) {
                $cleanSku = preg_replace('/^BOM-?/i', '', strtoupper(trim($product->sku)));
                $baseCode = 'BOM-' . $cleanSku;

                $num = $baseCode;
                $counter = 1;
                while (ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('bom_number', $num)
                    ->exists()) {
                    $counter++;
                    $num = $baseCode . '-' . sprintf('%02d', $counter);
                }
                return $num;
            }
        }

        $prefix = $prefix ?: 'BOM-';

        $maxVal = 0;
        $allBoms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('bom_number', 'like', "{$prefix}%")
            ->get();

        foreach ($allBoms as $b) {
            $numericPart = preg_replace('/[^0-9]/', '', substr($b->bom_number, strlen($prefix)));
            if (is_numeric($numericPart) && !empty($numericPart)) {
                $val = (int) $numericPart;
                if ($val > $maxVal) {
                    $maxVal = $val;
                }
            }
        }

        $nextVal = $maxVal + 1;
        $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);

        while (ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('bom_number', $num)
            ->exists()) {
            $nextVal++;
            $num = $prefix . str_pad((string)$nextVal, 6, '0', STR_PAD_LEFT);
        }

        return $num;
    }

    /**
     * Validate format constraints of a BOM number.
     */
    public function validateNumber(string $bomNumber, int $tenantId): bool
    {
        if (empty(trim($bomNumber)) || strlen($bomNumber) > 255) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9\-_#\/]+$/', $bomNumber);
    }

    /**
     * Check if a BOM number is already in use by another BOM for the tenant.
     */
    public function isDuplicate(string $bomNumber, int $tenantId, ?int $ignoreBomId = null): bool
    {
        $query = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('bom_number', $bomNumber);

        if ($ignoreBomId !== null) {
            $query->where('id', '!=', $ignoreBomId);
        }

        return $query->exists();
    }
}
