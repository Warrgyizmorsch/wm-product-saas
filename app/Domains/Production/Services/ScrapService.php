<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionScrapDisposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScrapService
{
    /**
     * Create scrap disposal record.
     */
    public function createScrapDisposal(int $tenantId, array $data): ProductionScrapDisposal
    {
        return DB::transaction(function () use ($tenantId, $data) {
            return ProductionScrapDisposal::create([
                'tenant_id' => $tenantId,
                'ncr_id' => $data['ncr_id'] ?? null,
                'category' => $data['category'],
                'reason_code' => $data['reason_code'],
                'quantity' => $data['quantity'],
                'cost' => $data['cost'] ?? 0.00,
                'status' => 'pending_approval',
            ]);
        });
    }

    /**
     * Approve and execute disposal.
     */
    public function approveDisposal(int $disposalId, int $userId, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($disposalId, $userId, $tenantId) {
            $disposal = ProductionScrapDisposal::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($disposalId);

            if ($disposal->status !== 'pending_approval') {
                throw new \InvalidArgumentException('Only pending scrap disposals can be approved.');
            }

            $disposal->update([
                'status' => 'approved',
                'disposed_at' => Carbon::now(),
                'disposed_by' => $userId,
            ]);
        });
    }
}
