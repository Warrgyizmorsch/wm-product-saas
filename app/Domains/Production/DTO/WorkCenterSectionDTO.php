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
        public readonly ?bool   $supports_manual_production = null,
        public readonly ?float  $manual_capacity_per_hour = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $machineIds = null;
        if (isset($data['sync_machines']) || array_key_exists('machine_ids', $data)) {
            $machineIds = isset($data['machine_ids']) && is_array($data['machine_ids'])
                ? array_values(array_unique(array_filter(array_map('intval', (array) $data['machine_ids']))))
                : [];
        }

        $supportsManual = array_key_exists('supports_manual_production', $data)
            ? filter_var($data['supports_manual_production'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $manualCapacity = null;
        if ($supportsManual === true) {
            $manualCapacity = (isset($data['manual_capacity_per_hour']) && is_numeric($data['manual_capacity_per_hour']))
                ? (float) $data['manual_capacity_per_hour']
                : null;
        } elseif ($supportsManual === false) {
            $manualCapacity = null;
        } elseif (array_key_exists('manual_capacity_per_hour', $data) && is_numeric($data['manual_capacity_per_hour'])) {
            $manualCapacity = (float) $data['manual_capacity_per_hour'];
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
            work_center_id:             (int) ($data['work_center_id'] ?? 0),
            name:                       trim($data['name'] ?? ''),
            code:                       strtoupper(trim($data['code'] ?? '')),
            workers_required:           $workersRequired,
            description:                $data['description'] ?? null,
            status:                     $data['status'] ?? 'active',
            shift_ids:                  $shiftIds,
            shift_workers:              [],
            machine_ids:                $machineIds,
            supports_manual_production: $supportsManual,
            manual_capacity_per_hour:   $manualCapacity,
        );
    }

    public function toArray(): array
    {
        $arr = [
            'work_center_id'  => $this->work_center_id,
            'name'            => $this->name,
            'code'            => $this->code,
            'assigned_shifts' => $this->shift_ids,
            'description'     => $this->description,
            'status'          => $this->status,
        ];

        if ($this->supports_manual_production !== null) {
            $arr['supports_manual_production'] = $this->supports_manual_production;
            $arr['manual_capacity_per_hour']   = $this->manual_capacity_per_hour;
        }

        return $arr;
    }
}
