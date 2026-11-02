<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RoutingDetailResource
 *
 * Full-fidelity detail resource for Routing including operation sequence and machine links.
 */
class RoutingDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
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
            'product'        => $this->whenLoaded('product', fn () => [
                'id'       => $this->product->id,
                'name'     => $this->product->name,
                'sku'      => $this->product->sku ?? null,
                'uom_name' => $this->product->uom?->name ?? null,
            ]),
            'operations'     => $this->whenLoaded('operations', fn () => $this->operations->map(fn ($op) => [
                'id'                      => $op->id,
                'sequence'                => (int) ($op->sequence ?? $op->operation_sequence),
                'operation_sequence'      => (int) ($op->sequence ?? $op->operation_sequence),
                'name'                    => $op->name ?? $op->operation_name,
                'operation_name'          => $op->name ?? $op->operation_name,
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
                'setup_time_minutes'      => (float) $op->setup_time_minutes,
                'processing_time_minutes' => (float) ($op->processing_time_minutes ?? $op->run_time_minutes),
                'run_time_minutes'        => (float) ($op->processing_time_minutes ?? $op->run_time_minutes),
                'wait_time_minutes'       => (float) $op->wait_time_minutes,
                'queue_time_minutes'      => (float) ($op->queue_time_minutes ?? $op->wait_time_minutes),
                'cost_per_hour'           => (float) ($op->cost_per_hour ?? ($op->labor_cost_rate + $op->machine_cost_rate)),
                'cost_per_unit'           => (float) $op->estimatedCostPerUnit(),
            ])),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
