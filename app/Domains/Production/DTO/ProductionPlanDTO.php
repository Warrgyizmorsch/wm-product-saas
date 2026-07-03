<?php

namespace App\Domains\Production\DTO;

class ProductionPlanDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly int     $product_id,
        public readonly float   $quantity,
        public readonly string  $start_date,
        public readonly string  $end_date,
        public readonly ?int    $bom_id = null,
        public readonly ?int    $routing_id = null,
        public readonly ?string $description = null,
        public readonly ?string $plan_number = null,
        public readonly ?string $status = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:        $data['name'],
            product_id:  (int) $data['product_id'],
            quantity:    (float) $data['quantity'],
            start_date:  $data['start_date'],
            end_date:    $data['end_date'],
            bom_id:      isset($data['bom_id']) && $data['bom_id'] !== '' ? (int) $data['bom_id'] : null,
            routing_id:  isset($data['routing_id']) && $data['routing_id'] !== '' ? (int) $data['routing_id'] : null,
            description: $data['description'] ?? null,
            plan_number: $data['plan_number'] ?? null,
            status:      $data['status'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'product_id'  => $this->product_id,
            'quantity'    => $this->quantity,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date,
            'bom_id'      => $this->bom_id,
            'routing_id'  => $this->routing_id,
            'description' => $this->description,
            'plan_number' => $this->plan_number,
            'status'      => $this->status,
        ];
    }
}
