<?php

namespace App\Domains\Production\DTO;

class WorkCenterDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $code,
        public readonly string  $status,
        public readonly ?string $work_center_type = null,
        public readonly ?string $description = null,
        public readonly ?string $location = null,
        public readonly ?float  $capacity_per_hour = null,
        public readonly float   $efficiency_percentage = 100.00,
        public readonly float   $cost_per_hour = 0.00,
        public readonly float   $overhead_rate = 0.00,
        public readonly ?int    $production_calendar_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:                   $data['name'],
            code:                   strtoupper(trim($data['code'])),
            status:                 $data['status'] ?? 'active',
            work_center_type:       $data['work_center_type'] ?? null,
            description:            $data['description'] ?? null,
            location:               $data['location'] ?? null,
            capacity_per_hour:      isset($data['capacity_per_hour']) ? (float) $data['capacity_per_hour'] : null,
            efficiency_percentage:  isset($data['efficiency_percentage']) ? (float) $data['efficiency_percentage'] : 100.00,
            cost_per_hour:          isset($data['cost_per_hour']) ? (float) $data['cost_per_hour'] : 0.00,
            overhead_rate:          isset($data['overhead_rate']) ? (float) $data['overhead_rate'] : 0.00,
            production_calendar_id: !empty($data['production_calendar_id']) ? (int) $data['production_calendar_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name'                   => $this->name,
            'code'                   => $this->code,
            'status'                 => $this->status,
            'work_center_type'       => $this->work_center_type,
            'description'            => $this->description,
            'location'               => $this->location,
            'capacity_per_hour'      => $this->capacity_per_hour,
            'efficiency_percentage'  => $this->efficiency_percentage,
            'cost_per_hour'          => $this->cost_per_hour,
            'overhead_rate'          => $this->overhead_rate,
            'production_calendar_id' => $this->production_calendar_id,
        ];
    }
}
