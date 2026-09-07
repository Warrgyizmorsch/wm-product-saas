<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionEco;

class ProductionEcoNumberService
{
    /**
     * Generate next ECO number for a tenant (e.g. ECO-2026-0001).
     */
    public function generateNextNumber(int $tenantId): string
    {
        $year = date('Y');
        $prefix = "ECO-{$year}-";

        $latestEco = ProductionEco::where('tenant_id', $tenantId)
            ->where('eco_number', 'LIKE', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestEco) {
            return "{$prefix}0001";
        }

        $lastSeq = (int) substr($latestEco->eco_number, strlen($prefix));
        $nextSeq = str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);

        return "{$prefix}{$nextSeq}";
    }
}
