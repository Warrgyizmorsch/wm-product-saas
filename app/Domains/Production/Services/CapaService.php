<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionNcr;
use Carbon\Carbon;

class CapaService
{
    public function __construct(
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create CAPA.
     */
    public function createCapa(int $tenantId, array $data): ProductionCapa
    {
        $capa = ProductionCapa::create(array_merge($data, [
            'tenant_id'   => $tenantId,
            'capa_number' => 'CAPA-'.strtoupper(uniqid()),
            'status'      => 'draft',
        ]));

        $this->eventService->writeEvent($tenantId, [
            'event_type'   => 'CAPA Created',
            'title'        => 'CAPA Opened',
            'description'  => "Corrective action CAPA {$capa->capa_number} opened.",
            'severity'     => 'warning',
            'event_source' => 'CapaService',
        ]);

        return $capa;
    }

    /**
     * Save RCA (5 Whys and Fishbone) details.
     */
    public function recordRca(int $capaId, array $fiveWhys, array $fishbone, ?int $tenantId = null): void
    {
        $capa = ProductionCapa::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($capaId);

        if ($capa->status === 'closed') {
            throw new \InvalidArgumentException('Closed CAPAs cannot be updated.');
        }

        $capa->update([
            'status' => 'active',
            'rca_analysis_json' => [
                'five_whys' => $fiveWhys,
                'fishbone' => $fishbone,
            ],
        ]);
    }

    /**
     * Close CAPA with effectiveness verification and e-signature.
     */
    public function closeCapa(int $capaId, int $userId, string $effectivenessReview, string $signature, ?int $tenantId = null): void
    {
        $capa = ProductionCapa::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($capaId);

        if (empty($effectivenessReview)) {
            throw new \InvalidArgumentException('Effectiveness review is required to close a CAPA.');
        }

        if ($capa->status === 'closed') {
            return;
        }

        $capa->update([
            'status' => 'closed',
            'effectiveness_review' => $effectivenessReview,
            'closed_by' => $userId,
            'closed_at' => Carbon::now(),
            'esignature_closed' => hash('sha256', $userId.$capaId.'closed'.now()->timestamp),
        ]);

        $this->eventService->writeEvent($capa->tenant_id, [
            'event_type' => 'CAPA Closed',
            'title' => 'CAPA Effectiveness Verified',
            'description' => "CAPA {$capa->capa_number} closed successfully.",
            'severity' => 'success',
            'event_source' => 'CapaService',
        ]);
    }

    /**
     * Check for repeat NCRs and trigger CAPA suggestion.
     */
    public function checkRepeatNcrs(int $tenantId, string $category, int $machineId): ?string
    {
        $count = ProductionNcr::where('tenant_id', $tenantId)
            ->where('category', $category)
            ->where('machine_id', $machineId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        if ($count >= 3) {
            return "Repeated non-conformance defect detected on machine #{$machineId} (Total: {$count} events in 30 days). Suggest logging a CAPA ticket immediately.";
        }

        return null;
    }
}
