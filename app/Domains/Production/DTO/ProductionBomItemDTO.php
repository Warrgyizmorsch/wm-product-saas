<?php

namespace App\Domains\Production\DTO;

class ProductionBomItemDTO
{
    public function __construct(
        public readonly int $material_id,
        public readonly float $quantity,
        public readonly int $uom_id,
        public readonly float $material_scrap_percentage = 0.00,
        public readonly bool $is_alternative = false,
        public readonly ?string $alternative_group = null,
        public readonly int $priority = 1,
        public readonly int $sequence = 1,
        public readonly ?string $effective_from = null,
        public readonly ?string $effective_to = null,
        public readonly ?string $notes = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            material_id: (int) $data['material_id'],
            quantity: (float) $data['quantity'],
            uom_id: (int) $data['uom_id'],
            material_scrap_percentage: isset($data['material_scrap_percentage']) ? (float) $data['material_scrap_percentage'] : (isset($data['wastage_percentage']) ? (float) $data['wastage_percentage'] : 0.00),
            is_alternative: !empty($data['is_alternative']),
            alternative_group: $data['alternative_group'] ?? null,
            priority: isset($data['priority']) ? (int) $data['priority'] : 1,
            sequence: isset($data['sequence']) ? (int) $data['sequence'] : 1,
            effective_from: !empty($data['effective_from']) ? $data['effective_from'] : null,
            effective_to: !empty($data['effective_to']) ? $data['effective_to'] : null,
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'material_id' => $this->material_id,
            'quantity' => $this->quantity,
            'uom_id' => $this->uom_id,
            'material_scrap_percentage' => $this->material_scrap_percentage,
            'is_alternative' => $this->is_alternative,
            'alternative_group' => $this->alternative_group,
            'priority' => $this->priority,
            'sequence' => $this->sequence,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
            'notes' => $this->notes,
        ];
    }
}
