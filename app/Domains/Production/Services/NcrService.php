<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionQualityInspection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NcrService
{
    public function __construct(
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create manual Non-Conformance Report (NCR).
     */
    public function createNcr(int $tenantId, array $data): ProductionNcr
    {
        $ncr = ProductionNcr::create(array_merge($data, [
            'tenant_id'  => $tenantId,
            'ncr_number' => 'NCR-'.strtoupper(uniqid()),
            'status'     => 'open',
        ]));

        $this->eventService->writeEvent($tenantId, [
            'production_order_id'            => $ncr->production_order_id,
            'production_order_operation_id'  => $ncr->production_order_operation_id,
            'machine_id'                     => $ncr->machine_id,
            'operator_id'                    => $ncr->operator_id,
            'event_type'                     => 'NCR Logged',
            'title'                          => 'Quality Defect Logged (NCR)',
            'description'                    => "Non-conformance report {$ncr->ncr_number} has been logged.",
            'severity'                       => 'warning',
            'event_source'                   => 'NcrService',
        ]);

        return $ncr;
    }

    /**
     * Create automatic NCR from failed inspection.
     */
    public function createAutoNcr(int $inspectionId): ProductionNcr
    {
        $inspection = ProductionQualityInspection::findOrFail($inspectionId);

        $ncr = ProductionNcr::create([
            'tenant_id' => $inspection->tenant_id,
            'ncr_number' => 'NCR-AUTO-'.strtoupper(uniqid()),
            'category' => 'process',
            'status' => 'open',
            'quality_inspection_id' => $inspection->id,
            'production_order_id' => $inspection->production_order_id,
            'production_order_operation_id' => $inspection->production_order_operation_id,
            'machine_id' => $inspection->machine_id,
            'operator_id' => $inspection->operator_id,
            'batch_id' => $inspection->batch_id,
            'serial_number_id' => $inspection->serial_number_id,
            'description' => "Automatic NCR generated due to failed inspection #{$inspection->id}.",
        ]);

        // Publish timeline event
        $this->eventService->writeEvent($ncr->tenant_id, [
            'production_order_id' => $ncr->production_order_id,
            'machine_id' => $ncr->machine_id,
            'event_type' => 'NCR Logged',
            'title' => 'Quality Defect Logged (NCR)',
            'description' => "Non-conformance report {$ncr->ncr_number} has been logged.",
            'severity' => 'warning',
            'event_source' => 'NcrService',
        ]);

        return $ncr;
    }

    /**
     * Process NCR Disposition.
     */
    public function processDisposition(int $ncrId, string $type, array $data, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($ncrId, $type, $data, $tenantId) {
            $ncr = ProductionNcr::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($ncrId);

            if ($ncr->status === 'closed') {
                throw new \InvalidArgumentException('Closed NCRs cannot be dispositioned.');
            }

            $ncr->update([
                'status' => 'disposition',
                'disposition_type' => $type, // use_as_is | scrap | rework | return_to_supplier
            ]);

            // Dispatch layout creation to respective services based on disposition type
            if ($type === 'rework') {
                $reworkService = app(ReworkService::class);
                $reworkService->createReworkOrder($ncr->tenant_id, $ncr->id, $data);
            } elseif ($type === 'scrap') {
                $scrapService = app(ScrapService::class);
                $scrapService->createScrapDisposal($ncr->tenant_id, [
                    'ncr_id' => $ncr->id,
                    'category' => $data['category'] ?? 'finished_good',
                    'reason_code' => $data['reason_code'] ?? 'defect',
                    'quantity' => $data['quantity'] ?? 1.0,
                    'cost' => $data['cost'] ?? 0.00,
                ]);
            }
        });
    }

    /**
     * Close NCR.
     */
    public function closeNcr(int $ncrId, int $userId, string $signature, ?int $tenantId = null): void
    {
        $ncr = ProductionNcr::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($ncrId);

        if ($ncr->status === 'closed') {
            return;
        }

        $ncr->update([
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => Carbon::now(),
            'esignature_closed' => hash('sha256', $userId.$ncrId.'closed'.now()->timestamp),
        ]);

        $this->eventService->writeEvent($ncr->tenant_id, [
            'production_order_id' => $ncr->production_order_id,
            'machine_id' => $ncr->machine_id,
            'event_type' => 'NCR Closed',
            'title' => 'Non-Conformance Resolved',
            'description' => "NCR {$ncr->ncr_number} closed and verified.",
            'style_class' => 'success',
            'severity' => 'success',
            'event_source' => 'NcrService',
        ]);
    }
}
