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
        public readonly ?string $department_name = null,
        public readonly ?string $location = null,
        public readonly ?float  $capacity_per_hour = null,
        public readonly float   $efficiency_percentage = 100.00,
        public readonly float   $cost_per_hour = 0.00,
        public readonly ?int    $parent_id = null,
        public readonly ?string $type = 'work_center',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:                  $data['name'],
            code:                  strtoupper(trim($data['code'])),
            status:                $data['status'] ?? 'active',
            work_center_type:      $data['work_center_type'] ?? null,
            description:           $data['description'] ?? null,
            department_name:       $data['department_name'] ?? null,
            location:              $data['location'] ?? null,
            capacity_per_hour:     isset($data['capacity_per_hour']) ? (float) $data['capacity_per_hour'] : null,
            efficiency_percentage: isset($data['efficiency_percentage']) ? (float) $data['efficiency_percentage'] : 100.00,
            cost_per_hour:         isset($data['cost_per_hour']) ? (float) $data['cost_per_hour'] : 0.00,
            parent_id:             !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            type:                  $data['type'] ?? 'work_center',
        );
    }

    public function toArray(): array
    {
        return [
            'name'                  => $this->name,
            'code'                  => $this->code,
            'status'                => $this->status,
            'work_center_type'      => $this->work_center_type,
            'description'           => $this->description,
            'department_name'       => $this->department_name,
            'location'              => $this->location,
            'capacity_per_hour'     => $this->capacity_per_hour,
            'efficiency_percentage' => $this->efficiency_percentage,
            'cost_per_hour'         => $this->cost_per_hour,
            'parent_id'             => $this->parent_id,
            'type'                  => $this->type,
        ];
    }
}
