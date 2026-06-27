<?php

namespace App\Domains\Production\DTO;

class ProductionBomDTO
{
    /**
     * @param ProductionBomItemDTO[] $items
     */
    public function __construct(
        public readonly string $bom_number,
        public readonly ?string $bom_name,
        public readonly string $bom_type,
        public readonly int $product_id,
        public readonly float $base_quantity,
        public readonly ?int $base_uom_id = null,
        public readonly string $version,
        public readonly ?string $revision_reason = null,
        public readonly ?int $routing_id = null,
        public readonly string $effective_date,
        public readonly ?string $expiry_date = null,
        public readonly ?string $notes = null,
        public readonly array $items = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $items[] = ProductionBomItemDTO::fromArray($item);
            }
        }

        return new self(
            bom_number: $data['bom_number'],
            bom_name: $data['bom_name'] ?? null,
            bom_type: $data['bom_type'] ?? 'manufacturing',
            product_id: (int) $data['product_id'],
            base_quantity: isset($data['base_quantity']) ? (float) $data['base_quantity'] : 1.0000,
            base_uom_id: !empty($data['base_uom_id']) ? (int) $data['base_uom_id'] : null,
            version: $data['version'] ?? '1.0.0',
            revision_reason: $data['revision_reason'] ?? null,
            routing_id: !empty($data['routing_id']) ? (int) $data['routing_id'] : null,
            effective_date: $data['effective_date'],
            expiry_date: $data['expiry_date'] ?? null,
            notes: $data['notes'] ?? null,
            items: $items,
        );
    }

    public function toArray(): array
    {
        return [
            'bom_number' => $this->bom_number,
            'bom_name' => $this->bom_name,
            'bom_type' => $this->bom_type,
            'product_id' => $this->product_id,
            'base_quantity' => $this->base_quantity,
            'base_uom_id' => $this->base_uom_id,
            'version' => $this->version,
            'revision_reason' => $this->revision_reason,
            'routing_id' => $this->routing_id,
            'effective_date' => $this->effective_date,
            'expiry_date' => $this->expiry_date,
            'notes' => $this->notes,
        ];
    }
}
