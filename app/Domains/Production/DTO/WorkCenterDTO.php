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
        public readonly float   $overhead_cost = 0.00,
        public readonly ?int    $production_calendar_id = null,
        public readonly int     $calculation_countdown_minutes = 10,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:                           $data['name'],
            code:                           strtoupper(trim($data['code'])),
            status:                         $data['status'] ?? 'active',
            work_center_type:               $data['work_center_type'] ?? null,
            description:                    $data['description'] ?? null,
            location:                       $data['location'] ?? null,
            overhead_cost:                  isset($data['overhead_cost']) && $data['overhead_cost'] !== '' ? (float) $data['overhead_cost'] : (isset($data['overhead_rate']) && $data['overhead_rate'] !== '' ? (float) $data['overhead_rate'] : 0.00),
            production_calendar_id:         !empty($data['production_calendar_id']) ? (int) $data['production_calendar_id'] : null,
            calculation_countdown_minutes:  isset($data['calculation_countdown_minutes']) ? max(1, (int) $data['calculation_countdown_minutes']) : 10,
        );
    }

    public function toArray(): array
    {
        return [
            'name'                          => $this->name,
            'code'                          => $this->code,
            'status'                        => $this->status,
            'work_center_type'              => $this->work_center_type,
            'description'                   => $this->description,
            'location'                      => $this->location,
            'overhead_cost'                 => $this->overhead_cost,
            'production_calendar_id'        => $this->production_calendar_id,
            'calculation_countdown_minutes' => $this->calculation_countdown_minutes,
        ];
    }
}
