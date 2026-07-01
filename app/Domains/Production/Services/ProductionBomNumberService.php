<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;

class ProductionBomNumberService
{
    /**
     * Generate the next automated BOM number for the tenant.
     */
    public function generateNextNumber(int $tenantId, ?string $prefix = null): string
    {
        $prefix = $prefix ?: 'BOM-';
        
        $latestBom = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('bom_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestBom) {
            $num = $prefix . str_pad('1', 6, '0', STR_PAD_LEFT);
        } else {
            $latestNumber = $latestBom->bom_number;
            $numericPart = substr($latestNumber, strlen($prefix));

            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len = max(6, strlen($numericPart));
                $num = $prefix . str_pad((string)$nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
        }

        while (ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('bom_number', $num)
            ->exists()) {
            $numericPart = substr($num, strlen($prefix));
            if (is_numeric($numericPart)) {
                $nextVal = (int) $numericPart + 1;
                $len = max(6, strlen($numericPart));
                $num = $prefix . str_pad((string)$nextVal, $len, '0', STR_PAD_LEFT);
            } else {
                $num = $prefix . mt_rand(100000, 999999);
            }
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
