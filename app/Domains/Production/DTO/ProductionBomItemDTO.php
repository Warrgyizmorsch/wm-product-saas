<?php

namespace App\Domains\Production\DTO;

class ProductionBomItemDTO
{
    public function __construct(
        public readonly int $material_id,
        public readonly float $quantity,
        public readonly int $uom_id,
        public readonly ?int $routing_operation_id = null,
        public readonly float $material_scrap_percentage = 0.00,
        public readonly bool $is_alternative = false,
        public readonly ?int $replaces_item_id = null,
        public readonly ?string $replaces_row_uid = null,
        public readonly ?string $row_uid = null,
        public readonly int $priority = 1,
        public readonly int $sequence = 1,
        public readonly ?string $notes = null,
        public readonly ?int $child_bom_id = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            material_id: (int) $data['material_id'],
            quantity: (float) $data['quantity'],
            uom_id: (int) $data['uom_id'],
            routing_operation_id: !empty($data['routing_operation_id']) ? (int) $data['routing_operation_id'] : null,
            material_scrap_percentage: isset($data['material_scrap_percentage'])
                ? (float) $data['material_scrap_percentage']
                : (isset($data['wastage_percentage']) ? (float) $data['wastage_percentage'] : 0.00),
            is_alternative: !empty($data['is_alternative']),
            replaces_item_id: !empty($data['replaces_item_id']) ? (int) $data['replaces_item_id'] : null,
            replaces_row_uid: !empty($data['replaces_row_uid']) ? (string) $data['replaces_row_uid'] : null,
            row_uid: !empty($data['row_uid']) ? (string) $data['row_uid'] : null,
            priority: isset($data['priority']) ? (int) $data['priority'] : 1,
            sequence: isset($data['sequence']) ? (int) $data['sequence'] : 1,
            notes: $data['notes'] ?? null,
            child_bom_id: !empty($data['child_bom_id']) ? (int) $data['child_bom_id'] : null,
        );
    }

    public function grossQuantity(): float
    {
        return round($this->quantity * (1.0 + ($this->material_scrap_percentage / 100.0)), 4);
    }

    public function toArray(): array
    {
        return [
            'material_id' => $this->material_id,
            'routing_operation_id' => $this->routing_operation_id,
            'quantity' => $this->quantity,
            'uom_id' => $this->uom_id,
            'material_scrap_percentage' => $this->material_scrap_percentage,
            'gross_quantity' => $this->grossQuantity(),
            'is_alternative' => $this->is_alternative,
            'replaces_item_id' => $this->replaces_item_id,
            'priority' => $this->priority,
            'sequence' => $this->sequence,
            'notes' => $this->notes,
            'child_bom_id' => $this->child_bom_id,
        ];
    }
}
