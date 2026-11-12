<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RoutingDetailResource
 *
 * RT-IMPL-17 (RT-28 rules 2–3): saved values only (nothing recalculated), one key per value, correct units,
 * external / traceability fields and routing-level saved totals.
 */
class RoutingDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $ops = $this->relationLoaded('operations') ? $this->operations : collect();
        $primary = $ops->where('is_alternative', false);
        $num = fn($v) => $v !== null ? round((float) $v, 4) : null;

        return [
            'id'             => $this->id,
            'routing_number' => $this->routing_number,
            'name'           => $this->name,
            'version'        => $this->version,
            'revision'       => (int) $this->revision,
            'is_default'     => (bool) $this->is_default,
            'status'         => $this->status,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to'   => $this->effective_to?->format('Y-m-d'),
            'description'    => $this->description,
            'approved_by'    => $this->approved_by,
            'approved_at'    => $this->approved_at?->toIso8601String(),
            'cost_snapshot'  => $this->cost_snapshot, // costs at approval (RT-IMPL-14)
            // Saved totals for 1 unit — primary route only (alternatives excluded)
            'totals'         => $this->whenLoaded('operations', fn () => [
                'cost_per_unit'               => round((float) $primary->sum(fn($o) => (float) ($o->cost_per_unit ?? 0)), 4),
                'labor_cost_per_unit'         => round((float) $primary->sum(fn($o) => (float) ($o->labor_cost_per_unit ?? 0)), 4),
                'machine_cost_per_unit'       => round((float) $primary->sum(fn($o) => (float) ($o->machine_cost_per_unit ?? 0)), 4),
                'setup_cost_per_run'          => round((float) $primary->sum(fn($o) => (float) ($o->setup_cost_per_run ?? 0)), 4),
                'setup_time_minutes_per_run'  => round((float) $primary->sum(fn($o) => (float) $o->setup_time_minutes), 4),
                'cycle_time_minutes_per_unit' => round((float) $primary->sum(fn($o) => (float) ($o->setup_time_per_piece_minutes ?? 0) + (float) $o->processing_time_minutes), 4),
                'costs_not_calculated'        => $ops->contains(fn($o) => $o->costsNotCalculated()),
            ]),
            'costs_outdated' => $this->whenLoaded('operations', fn () => (bool) (app(\App\Domains\Production\Services\RoutingCostStatusService::class)->check($this->resource)['outdated'] ?? false)),
            'boms'           => $this->whenLoaded('boms', fn () => $this->boms->map(fn ($b) => [
                'id' => $b->id, 'bom_number' => $b->bom_number, 'version' => $b->version, 'status' => $b->status,
            ])),
            'operations'     => $this->whenLoaded('operations', fn () => $ops->map(fn ($op) => [
                'id'                      => $op->id,
                'sequence'                => (int) $op->sequence,
                'name'                    => $op->name,
                'operation_number'        => $op->operation_number,
                'operation_type'          => $op->operation_type,
                'description'             => $op->description,
                'work_center_id'          => $op->work_center_id,
                'work_center'             => $op->workCenter ? [
                    'id'   => $op->workCenter->id,
                    'name' => $op->workCenter->name,
                    'code' => $op->workCenter->code,
                ] : null,
                'section_id'              => $op->section_id,
                'section'                 => $op->section ? [
                    'id'   => $op->section->id,
                    'name' => $op->section->name,
                    'code' => $op->section->code,
                ] : null,
                'machine_id'              => $op->machine_id,
                'machine'                 => $op->machine ? [
                    'id'   => $op->machine->id,
                    'name' => $op->machine->name,
                    'code' => $op->machine->code,
                ] : null,
                'section_manual_production_id' => $op->section_manual_production_id,
                'manual_production'       => $op->manualProduction ? [
                    'id'         => $op->manualProduction->id,
                    'batch_name' => $op->manualProduction->batch_name,
                    'shift_id'   => $op->manualProduction->shift_id,
                ] : null,
                'is_alternative'          => (bool) $op->is_alternative,
                'replaces_operation_id'   => $op->replaces_operation_id,
                'priority'                => $op->priority ? (int) $op->priority : null,
                'previous_operation_id'   => $op->previous_operation_id,
                'is_parallel'             => (bool) $op->is_parallel,
                'parallel_group'          => $op->parallel_group,
                // times (minutes) — setup per operation runs once per production run; setup per piece per unit
                'setup_time_minutes'           => (float) $op->setup_time_minutes,
                'setup_time_per_piece_minutes' => $op->setup_time_per_piece_minutes !== null ? (float) $op->setup_time_per_piece_minutes : null,
                'processing_time_minutes'      => (float) $op->processing_time_minutes,
                'wait_time_minutes'            => (float) $op->wait_time_minutes,
                'expected_yield_percentage'    => (float) $op->expected_yield_percentage,
                // rates are per MINUTE (base currency)
                'labor_cost_rate_per_minute'   => $num($op->labor_cost_rate),
                'machine_cost_rate_per_minute' => $num($op->machine_cost_rate),
                // saved figures (calculated once, checked on save — RT-IMPL-14)
                'cost_per_unit'           => $num($op->cost_per_unit),
                'labor_cost_per_unit'     => $num($op->labor_cost_per_unit),
                'machine_cost_per_unit'   => $num($op->machine_cost_per_unit),
                'setup_cost_per_run'      => $num($op->setup_cost_per_run),
                'costs_not_calculated'    => $op->costsNotCalculated(),
                // external
                'is_external'                    => (bool) $op->is_external,
                'vendor'                         => $op->is_external && $op->vendor ? ['id' => $op->vendor->id, 'name' => $op->vendor->name] : null,
                'subcontract_cost_per_unit'      => $op->is_external ? $num($op->subcontract_cost_per_unit) : null,
                'subcontract_lead_time_days'     => $op->is_external ? (int) $op->subcontract_lead_time_days : null,
                'dispatch_buffer_days'           => $op->is_external ? (int) $op->dispatch_buffer_days : null,
                'return_buffer_days'             => $op->is_external ? (int) $op->return_buffer_days : null,
                'material_supply_type'           => $op->is_external ? $op->material_supply_type : null,
                'subcontract_service_product_id' => $op->is_external ? $op->subcontract_service_product_id : null,
                // traceability
                'resource_snapshot'       => $op->resource_snapshot,
                'section_calculation_id'  => $op->section_calculation_id,
                'section_snapshot_number' => $op->section_snapshot_number,
                'costs_calculated_at'     => $op->costs_calculated_at?->toIso8601String(),
            ])),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
