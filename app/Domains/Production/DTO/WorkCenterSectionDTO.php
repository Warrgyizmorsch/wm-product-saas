<?php

namespace App\Domains\Production\DTO;

class WorkCenterSectionDTO
{
    public function __construct(
        public readonly int     $work_center_id,
        public readonly string  $name,
        public readonly string  $code,
        public readonly int     $workers_required = 1,
        public readonly ?string $description = null,
        public readonly string  $status = 'active',
        public readonly array   $shift_ids = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            work_center_id:   (int) ($data['work_center_id'] ?? 0),
            name:             trim($data['name'] ?? ''),
            code:             strtoupper(trim($data['code'] ?? '')),
            workers_required: max(1, (int) ($data['workers_required'] ?? 1)),
            description:      $data['description'] ?? null,
            status:           $data['status'] ?? 'active',
            shift_ids:        isset($data['shifts']) ? array_values(array_unique(array_filter(array_map('intval', (array) $data['shifts'])))) : [],
        );
    }

    public function toArray(): array
    {
        return [
            'work_center_id'   => $this->work_center_id,
            'name'             => $this->name,
            'code'             => $this->code,
            'workers_required' => $this->workers_required,
            'description'      => $this->description,
            'status'           => $this->status,
        ];
    }
}
