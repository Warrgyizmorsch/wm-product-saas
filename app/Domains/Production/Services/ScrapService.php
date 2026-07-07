<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionScrapDisposal;
use Carbon\Carbon;

class ScrapService
{
    /**
     * Create scrap disposal record.
     */
    public function createScrapDisposal(int $tenantId, array $data): ProductionScrapDisposal
    {
        return ProductionScrapDisposal::create([
            'tenant_id'   => $tenantId,
            'ncr_id'      => $data['ncr_id'] ?? null,
            'category'    => $data['category'],
            'reason_code' => $data['reason_code'],
            'quantity'    => $data['quantity'],
            'cost'        => $data['cost'] ?? 0.00,
            'status'      => 'pending_approval',
        ]);
    }

    /**
     * Approve and execute disposal.
     */
    public function approveDisposal(int $disposalId, int $userId): void
    {
        $disposal = ProductionScrapDisposal::findOrFail($disposalId);

        $disposal->update([
            'status'      => 'approved',
            'disposed_at' => Carbon::now(),
            'disposed_by' => $userId,
        ]);
    }
}
