<?php

namespace App\Domains\Production\DTO;

class RoutingDTO
{
    /**
     * @param RoutingOperationDTO[] $operations
     */
    public function __construct(
        public readonly string  $name,
        public readonly int     $product_id,
        public readonly string  $version,
        public readonly string  $effective_from,
        public readonly ?string $routing_number = null,
        public readonly int     $revision = 0,
        public readonly bool    $is_default = true,
        public readonly ?string $effective_to = null,
        public readonly ?string $description = null,
        public readonly array   $operations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $operations = [];
        if (isset($data['operations']) && is_array($data['operations'])) {
            foreach ($data['operations'] as $op) {
                $operations[] = RoutingOperationDTO::fromArray($op);
            }
        }

        return new self(
            name:           $data['name'],
            product_id:     (int) $data['product_id'],
            version:        $data['version'] ?? '1.0.0',
            effective_from: $data['effective_from'],
            routing_number: $data['routing_number'] ?? null,
            revision:       isset($data['revision']) ? (int) $data['revision'] : 0,
            is_default:     isset($data['is_default']) ? (bool) $data['is_default'] : true,
            effective_to:   $data['effective_to'] ?? null,
            description:    $data['description'] ?? null,
            operations:     $operations,
        );
    }

    public function toArray(): array
    {
        return [
            'name'           => $this->name,
            'product_id'     => $this->product_id,
            'version'        => $this->version,
            'effective_from' => $this->effective_from,
            'routing_number' => $this->routing_number,
            'revision'       => $this->revision,
            'is_default'     => $this->is_default,
            'effective_to'   => $this->effective_to,
            'description'    => $this->description,
        ];
    }
}
