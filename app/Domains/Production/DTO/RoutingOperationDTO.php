<?php

namespace App\Domains\Production\DTO;

class RoutingOperationDTO
{
    public function __construct(
        public readonly int     $sequence,
        public readonly string  $operation_number,
        public readonly string  $name,
        public readonly string  $operation_type,
        public readonly int     $work_center_id,
        public readonly ?int    $machine_id = null,
        public readonly float   $setup_time_minutes = 0.0,
        public readonly float   $processing_time_minutes = 0.0,
        public readonly float   $wait_time_minutes = 0.0,
        public readonly float   $expected_yield_percentage = 100.0,
        public readonly float   $labor_cost_rate = 0.0,
        public readonly float   $machine_cost_rate = 0.0,
        public readonly ?string $description = null,
        public readonly ?string $instructions = null,
        public readonly bool    $quality_required = false,
        public readonly bool    $is_external = false,
        public readonly ?int    $vendor_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $sequence = (int) ($data['sequence'] ?? 10);
        return new self(
            sequence:                  $sequence,
            operation_number:          $data['operation_number'] ?? 'OP-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            name:                      $data['name'],
            operation_type:            $data['operation_type'] ?? 'manufacturing',
            work_center_id:            (int) $data['work_center_id'],
            machine_id:                !empty($data['machine_id']) ? (int) $data['machine_id'] : null,
            setup_time_minutes:        isset($data['setup_time_minutes']) ? (float) $data['setup_time_minutes'] : 0.0,
            processing_time_minutes:   isset($data['processing_time_minutes']) ? (float) $data['processing_time_minutes'] : 0.0,
            wait_time_minutes:         isset($data['wait_time_minutes']) ? (float) $data['wait_time_minutes'] : 0.0,
            expected_yield_percentage: isset($data['expected_yield_percentage']) ? (float) $data['expected_yield_percentage'] : 100.0,
            labor_cost_rate:           isset($data['labor_cost_rate']) ? (float) $data['labor_cost_rate'] : 0.0,
            machine_cost_rate:         isset($data['machine_cost_rate']) ? (float) $data['machine_cost_rate'] : 0.0,
            description:               $data['description'] ?? null,
            instructions:              $data['instructions'] ?? null,
            quality_required:          !empty($data['quality_required']),
            is_external:               !empty($data['is_external']),
            vendor_id:                 !empty($data['vendor_id']) ? (int) $data['vendor_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'sequence'                  => $this->sequence,
            'operation_number'          => $this->operation_number,
            'name'                      => $this->name,
            'operation_type'            => $this->operation_type,
            'work_center_id'            => $this->work_center_id,
            'machine_id'                => $this->machine_id,
            'setup_time_minutes'        => $this->setup_time_minutes,
            'processing_time_minutes'   => $this->processing_time_minutes,
            'wait_time_minutes'         => $this->wait_time_minutes,
            'expected_yield_percentage' => $this->expected_yield_percentage,
            'labor_cost_rate'           => $this->labor_cost_rate,
            'machine_cost_rate'         => $this->machine_cost_rate,
            'description'               => $this->description,
            'instructions'              => $this->instructions,
            'quality_required'          => $this->quality_required,
            'is_external'               => $this->is_external,
            'vendor_id'                 => $this->vendor_id,
        ];
    }
}
