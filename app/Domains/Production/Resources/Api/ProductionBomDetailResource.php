<?php

namespace App\Domains\Production\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductionBomDetailResource
 *
 * Full-fidelity detail resource for Bill of Materials including component lines.
 */
class ProductionBomDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'bom_number'      => $this->bom_number,
            'bom_name'        => $this->bom_name,
            'bom_type'        => $this->bom_type,
            'usage_context'   => $this->usage_context,
            'base_quantity'   => (float) $this->base_quantity,
            'version'         => $this->version,
            'revision'        => (int) $this->revision,
            'revision_reason' => $this->revision_reason,
            'status'          => $this->status,
            'effective_date'  => $this->effective_date?->format('Y-m-d'),
            'expiry_date'     => $this->expiry_date?->format('Y-m-d'),
            'notes'           => $this->notes,
            'routing_id'      => $this->routing_id ?? $this->routing?->id,
            'routing_advisory'=> $this->routing_advisory,
            'routing'         => $this->whenLoaded('routing', fn () => [
                'id'             => $this->routing->id,
                'routing_number' => $this->routing->routing_number,
                'name'           => $this->routing->name,
                'status'         => $this->routing->status,
            ]),
            'product'         => $this->whenLoaded('product', fn () => [
                'id'       => $this->product->id,
                'name'     => $this->product->name,
                'sku'      => $this->product->sku ?? null,
                'uom_name' => $this->product->uom?->name ?? null,
            ]),
            'base_uom'        => $this->whenLoaded('baseUom', fn () => [
                'id'   => $this->baseUom->id,
                'name' => $this->baseUom->name,
                'code' => $this->baseUom->code ?? null,
            ]),
            'allocations'     => $this->whenLoaded('operationMaterials', fn () => $this->operationMaterials->map(fn ($om) => $this->mapOperationMaterial($om))),
            'items'           => $this->when(
                $this->relationLoaded('operationMaterials') || $this->relationLoaded('items'),
                function () {
                    if ($this->relationLoaded('operationMaterials') && $this->operationMaterials->isNotEmpty()) {
                        return $this->operationMaterials->map(fn ($om) => $this->mapOperationMaterial($om));
                    }

                    return $this->items->map(fn ($item) => [
                        'id'                        => $item->id,
                        'material_id'               => $item->material_id,
                        'item_product_id'           => $item->material_id,
                        'product_id'                => $item->material_id,
                        'item_product_name'         => $item->material?->name ?? $item->product?->name ?? null,
                        'item_sku'                  => $item->material?->sku ?? $item->product?->sku ?? null,
                        'quantity'                  => (float) $item->quantity,
                        'gross_quantity'            => round((float) $item->quantity * (1 + ((float) ($item->material_scrap_percentage ?? 0) / 100)), 4),
                        'uom_id'                    => $item->uom_id,
                        'uom_name'                  => $item->uom?->name ?? null,
                        'material_scrap_percentage' => (float) $item->material_scrap_percentage,
                        'scrap_percentage'          => (float) $item->material_scrap_percentage,
                        'sequence'                  => (int) $item->sequence,
                        'is_critical'               => (bool) ($item->is_critical ?? false),
                        'is_alternative'            => (bool) $item->is_alternative,
                        'replaces_item_id'          => $item->replaces_item_id,
                        'priority'                  => (int) ($item->priority ?? 1),
                        'child_bom_id'              => $item->child_bom_id,
                        'notes'                     => $item->notes,
                    ]);
                }
            ),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }

    private function mapOperationMaterial($om): array
    {
        return [
            'id'                           => $om->id,
            'routing_operation_id'         => $om->routing_operation_id,
            'operation_number'             => $om->routingOperation?->operation_number ?? $om->routingOperation?->sequence,
            'operation_name'               => $om->routingOperation?->operation_name,
            'operation_deleted'            => (bool) ($om->routingOperation?->trashed() ?? false),
            'bom_item_id'                  => $om->bom_item_id,
            'material_id'                  => $om->material_id,
            'item_product_id'              => $om->material_id,
            'product_id'                   => $om->material_id,
            'item_product_name'            => $om->material?->name ?? null,
            'item_sku'                     => $om->material?->sku ?? null,
            'quantity'                     => (float) $om->quantity,
            'gross_quantity'               => (float) ($om->gross_quantity ?? $om->calculateGrossQuantity()),
            'uom_id'                       => $om->uom_id,
            'uom_name'                     => $om->uom?->name ?? null,
            'material_scrap_percentage'    => (float) $om->material_scrap_percentage,
            'scrap_percentage'             => (float) $om->material_scrap_percentage,
            'sequence'                     => (int) $om->sequence,
            'is_critical'                  => (bool) ($om->bomItem?->is_critical ?? false),
            'is_alternative'               => (bool) $om->is_alternative,
            'replaces_routing_material_id' => $om->replaces_routing_material_id,
            'replaces_item_id'             => $om->replaces_routing_material_id ?? $om->bomItem?->replaces_item_id,
            'priority'                     => (int) ($om->priority ?? 1),
            'child_bom_id'                 => $om->child_bom_id,
            'notes'                        => $om->notes,
        ];
    }
}
