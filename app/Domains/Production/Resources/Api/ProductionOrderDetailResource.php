<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionOrderDetailResource
 *
 * Full-fidelity detail resource for production order deep dive.
 */
class ProductionOrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'order_number'      => $this->order_number,
            'status'            => $this->status,
            'production_model'  => $this->production_model,
            'production_mode'   => $this->production_mode,
            'quantity_ordered'  => (float) $this->quantity_ordered,
            'quantity_produced' => (float) $this->quantity_produced,
            'quantity_rejected' => (float) $this->quantity_rejected,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'start_date'        => $this->start_date?->format('Y-m-d'),
            'end_date'          => $this->end_date?->format('Y-m-d'),
            'actual_start_date' => $this->actual_start_date?->format('Y-m-d H:i:s'),
            'actual_end_date'   => $this->actual_end_date?->format('Y-m-d H:i:s'),
            'description'       => $this->description,
            'barcode'           => $this->barcode,
            'qr_code'           => $this->qr_code,
            'product'           => $this->whenLoaded('product', fn () => [
                'id'       => $this->product->id,
                'name'     => $this->product->name,
                'sku'      => $this->product->sku ?? null,
                'uom_name' => $this->product->uom?->name ?? null,
            ]),
            'bom'               => $this->whenLoaded('bom', fn () => [
                'id'            => $this->bom->id,
                'bom_number'    => $this->bom->bom_number,
                'version'       => $this->bom->version,
                'base_quantity' => (float) $this->bom->base_quantity,
            ]),
            'routing'           => $this->whenLoaded('routing', fn () => [
                'id'             => $this->routing->id,
                'routing_number' => $this->routing->routing_number,
                'name'           => $this->routing->name,
            ]),
            'operations'        => $this->whenLoaded('operations', fn () => $this->operations->map(fn ($op) => [
                'id'                 => $op->id,
                'operation_sequence' => $op->operation_sequence,
                'operation_name'     => $op->operation_name,
                'status'             => $op->status,
                'work_center'        => $op->workCenter ? [
                    'id'   => $op->workCenter->id,
                    'name' => $op->workCenter->name,
                    'code' => $op->workCenter->code,
                ] : null,
                'machine'            => $op->machine ? [
                    'id'   => $op->machine->id,
                    'name' => $op->machine->name,
                    'code' => $op->machine->code,
                ] : null,
                'planned_quantity'   => (float) $op->planned_quantity,
                'completed_quantity' => (float) $op->completed_quantity,
                'scrapped_quantity'  => (float) $op->scrapped_quantity,
                'actual_start_time'  => $op->actual_start_time?->toIso8601String(),
                'actual_end_time'    => $op->actual_end_time?->toIso8601String(),
            ])),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
