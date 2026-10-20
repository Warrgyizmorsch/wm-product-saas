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
        public readonly bool    $supports_manual_production = false,
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

        $supportsManual = filter_var($data['supports_manual_production'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $manualCapacity = ($supportsManual && isset($data['manual_capacity_per_hour']) && is_numeric($data['manual_capacity_per_hour']))
            ? (float) $data['manual_capacity_per_hour']
            : null;

        $shiftIds = [];
        $shiftWorkers = [];

        if (isset($data['shifts']) && is_array($data['shifts'])) {
            foreach ($data['shifts'] as $key => $val) {
                if (is_array($val)) {
                    $sId = (int) ($val['id'] ?? $val['shift_id'] ?? $key);
                    $shiftIds[] = $sId;
                    if (isset($val['workers_required']) && is_numeric($val['workers_required'])) {
                        $shiftWorkers[$sId] = (int) $val['workers_required'];
                    }
                } else {
                    $sId = (int) $val;
                    $shiftIds[] = $sId;
                }
            }
        }
        $shiftIds = array_values(array_unique(array_filter($shiftIds)));

        if (isset($data['shift_workers']) && is_array($data['shift_workers'])) {
            foreach ($data['shift_workers'] as $sId => $cnt) {
                if (is_numeric($cnt) && (int) $cnt > 0) {
                    $shiftWorkers[(int) $sId] = (int) $cnt;
                }
            }
        }

        // Section-level fallback workers_required is max of shift workers, or provided, or default 1
        $workersRequired = !empty($shiftWorkers)
            ? max(array_values($shiftWorkers))
            : max(1, (int) ($data['workers_required'] ?? 1));

        return new self(
            work_center_id:             (int) ($data['work_center_id'] ?? 0),
            name:                       trim($data['name'] ?? ''),
            code:                       strtoupper(trim($data['code'] ?? '')),
            workers_required:           $workersRequired,
            description:                $data['description'] ?? null,
            status:                     $data['status'] ?? 'active',
            shift_ids:                  $shiftIds,
            shift_workers:              $shiftWorkers,
            machine_ids:                $machineIds,
            supports_manual_production: $supportsManual,
            manual_capacity_per_hour:   $manualCapacity,
        );
    }

    public function toArray(): array
    {
        return [
            'work_center_id'             => $this->work_center_id,
            'name'                       => $this->name,
            'code'                       => $this->code,
            'workers_required'           => $this->workers_required,
            'supports_manual_production' => $this->supports_manual_production,
            'manual_capacity_per_hour'   => $this->manual_capacity_per_hour,
            'description'                => $this->description,
            'status'                     => $this->status,
        ];
    }
}
