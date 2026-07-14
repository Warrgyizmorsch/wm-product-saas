<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionDeviation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeviationService
{
    /**
     * Create Deviation request.
     */
    public function createDeviation(int $tenantId, array $data): ProductionDeviation
    {
        return DB::transaction(function () use ($tenantId, $data) {
            return ProductionDeviation::create(array_merge($data, [
                'tenant_id' => $tenantId,
                'deviation_number' => 'DEV-'.strtoupper(uniqid()),
                'status' => 'draft',
            ]));
        });
    }

    /**
     * Approve Deviation request.
     */
    public function approveDeviation(int $deviationId, int $userId, string $signature, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($deviationId, $userId, $tenantId) {
            $deviation = ProductionDeviation::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($deviationId);

            if ($deviation->status === 'approved') {
                return;
            }

            if ($deviation->expiration_date && $deviation->expiration_date->isPast()) {
                throw new \InvalidArgumentException('Expired deviations cannot be approved.');
            }

            $deviation->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
                'esignature' => hash('sha256', $userId.$deviationId.'approved'.now()->timestamp),
            ]);
        });
    }
}
