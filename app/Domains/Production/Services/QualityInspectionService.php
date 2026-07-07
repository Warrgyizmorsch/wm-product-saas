<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionQualityPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QualityInspectionService
{
    public function __construct(
        private readonly NcrService $ncrService,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Create a Quality Inspection.
     */
    public function createInspection(int $tenantId, array $data): ProductionQualityInspection
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $plan = ProductionQualityPlan::where('tenant_id', $tenantId)->findOrFail($data['quality_plan_id']);

            $inspection = ProductionQualityInspection::create([
                'tenant_id'                     => $tenantId,
                'quality_plan_id'               => $plan->id,
                'stage'                         => $data['stage'],
                'status'                        => 'draft',
                'result'                        => 'passed',
                'production_order_id'           => $data['production_order_id'] ?? null,
                'production_order_operation_id' => $data['production_order_operation_id'] ?? null,
                'machine_id'                    => $data['machine_id'] ?? null,
                'operator_id'                   => $data['operator_id'] ?? null,
                'batch_id'                      => $data['batch_id'] ?? null,
                'serial_number_id'              => $data['serial_number_id'] ?? null,
            ]);

            // Copy plan parameters as blank result rows
            foreach ($plan->parameters as $param) {
                ProductionQualityInspectionResult::create([
                    'tenant_id'                 => $tenantId,
                    'quality_inspection_id'     => $inspection->id,
                    'quality_plan_parameter_id' => $param->id,
                    'result'                    => 'passed',
                ]);
            }

            return $inspection;
        });
    }

    /**
     * Record results for inspection parameters and evaluate pass/fail criteria.
     */
    public function recordResults(int $inspectionId, array $resultsData): void
    {
        DB::transaction(function () use ($inspectionId, $resultsData) {
            $inspection = ProductionQualityInspection::findOrFail($inspectionId);
            $overallPassed = true;

            foreach ($resultsData as $res) {
                $resultRow = ProductionQualityInspectionResult::where('quality_inspection_id', $inspectionId)
                    ->where('quality_plan_parameter_id', $res['parameter_id'])
                    ->firstOrFail();

                $param = $resultRow->parameter;
                $passed = true;

                // Evaluate parameter specific rules
                if ($param->type === 'numeric') {
                    $val = (float) $res['value_numeric'];
                    if (($param->min_value !== null && $val < $param->min_value) || 
                        ($param->max_value !== null && $val > $param->max_value)) {
                        $passed = false;
                    }
                    $resultRow->update([
                        'recorded_value_numeric' => $val,
                        'result'                 => $passed ? 'passed' : 'failed',
                    ]);
                } elseif ($param->type === 'pass_fail') {
                    $passed = (bool) $res['value_pass'];
                    $resultRow->update([
                        'recorded_value_pass' => $passed,
                        'result'              => $passed ? 'passed' : 'failed',
                    ]);
                } else {
                    $resultRow->update([
                        'recorded_value_text' => $res['value_text'],
                        'result'              => 'passed',
                    ]);
                }

                if (!$passed) {
                    $overallPassed = false;
                }
            }

            $inspection->update([
                'result' => $overallPassed ? 'passed' : 'failed',
                'status' => 'submitted',
            ]);
        });
    }

    /**
     * Approve and finalize the inspection. Creates NCR automatically on failure.
     */
    public function approveInspection(int $inspectionId, int $userId, string $signature): void
    {
        DB::transaction(function () use ($inspectionId, $userId, $signature) {
            $inspection = ProductionQualityInspection::findOrFail($inspectionId);

            $inspection->update([
                'status'     => 'approved',
                'audited_by' => $userId,
                'audited_at' => Carbon::now(),
                'esignature' => hash('sha256', $userId . $inspectionId . 'approved' . now()->timestamp),
            ]);

            // Timeline event integration
            $this->eventService->writeEvent($inspection->tenant_id, [
                'production_order_id' => $inspection->production_order_id,
                'machine_id'          => $inspection->machine_id,
                'event_type'          => 'Inspection Finalized',
                'title'               => 'Quality Inspection Audited',
                'description'         => "Inspection #{$inspection->id} finalized with result: " . strtoupper($inspection->result),
                'severity'            => $inspection->result === 'passed' ? 'success' : 'warning',
                'event_source'        => 'QualityInspectionService',
            ]);

            // Auto NCR creation if failed
            if ($inspection->result === 'failed') {
                $this->ncrService->createAutoNcr($inspection->id);
            }
        });
    }
}
