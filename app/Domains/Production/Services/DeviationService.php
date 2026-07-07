<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionDeviation;
use Carbon\Carbon;

class DeviationService
{
    /**
     * Create Deviation request.
     */
    public function createDeviation(int $tenantId, array $data): ProductionDeviation
    {
        return ProductionDeviation::create(array_merge($data, [
            'tenant_id'        => $tenantId,
            'deviation_number' => 'DEV-' . strtoupper(uniqid()),
            'status'           => 'draft',
        ]));
    }

    /**
     * Approve Deviation request.
     */
    public function approveDeviation(int $deviationId, int $userId, string $signature): void
    {
        $deviation = ProductionDeviation::findOrFail($deviationId);

        $deviation->update([
            'status'      => 'approved',
            'approved_by' => $userId,
            'approved_at' => Carbon::now(),
            'esignature'  => hash('sha256', $userId . $deviationId . 'approved' . now()->timestamp),
        ]);
    }
}
