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
        public readonly array   $shift_workers = [],
        public readonly ?array  $machine_ids = null,
        public readonly mixed   $supports_manual_production = null,
        public readonly mixed   $manual_capacity_per_hour = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $machineIds = null;
        if (isset($data['sync_machines']) || array_key_exists('machine_ids', $data)) {
            $machineIds = isset($data['machine_ids']) && is_array($data['machine_ids'])
                ? array_values(array_unique(array_filter(array_map('intval', (array) $data['machine_ids']))))
                : [];
        }

        $shiftIds = [];

        if (isset($data['shifts']) && is_array($data['shifts'])) {
            foreach ($data['shifts'] as $key => $val) {
                if (is_array($val)) {
                    $sId = (int) ($val['id'] ?? $val['shift_id'] ?? $key);
                    $shiftIds[] = $sId;
                } else {
                    $sId = (int) $val;
                    $shiftIds[] = $sId;
                }
            }
        }
        $shiftIds = array_values(array_unique(array_filter($shiftIds)));

        $workersRequired = max(1, (int) ($data['workers_required'] ?? 1));

        return new self(
            work_center_id:   (int) ($data['work_center_id'] ?? 0),
            name:             trim($data['name'] ?? ''),
            code:             strtoupper(trim($data['code'] ?? '')),
            workers_required: $workersRequired,
            description:      $data['description'] ?? null,
            status:           $data['status'] ?? 'active',
            shift_ids:        $shiftIds,
            shift_workers:    [],
            machine_ids:      $machineIds,
        );
    }

    public function toArray(): array
    {
        return [
            'work_center_id'  => $this->work_center_id,
            'name'            => $this->name,
            'code'            => $this->code,
            'assigned_shifts' => $this->shift_ids,
            'description'     => $this->description,
            'status'          => $this->status,
        ];
    }
}
