<?php

namespace App\Domains\Production\DTO;

class RoutingOperationDTO
{
    public function __construct(
        public readonly int     $sequence,
        public readonly string  $operation_number,
        public readonly string  $name,
        public readonly string  $operation_type,
        public readonly ?int    $work_center_id = null,
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
        public readonly bool    $overlap_enabled = false,
        public readonly float   $transfer_batch_quantity = 0.0,
        public readonly int     $transfer_lag_minutes = 0,
        public readonly int     $subcontract_lead_time_days = 0,
        public readonly float   $subcontract_cost_per_unit = 0.0,
        public readonly ?int    $subcontract_service_product_id = null,
        public readonly ?string $material_supply_type = null,
        public readonly int     $dispatch_buffer_days = 0,
        public readonly int     $return_buffer_days = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        $sequence = (int) ($data['sequence'] ?? 10);
        return new self(
            sequence:                       $sequence,
            operation_number:               $data['operation_number'] ?? 'OP-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            name:                           $data['name'],
            operation_type:                 $data['operation_type'] ?? 'manufacturing',
            work_center_id:                 !empty($data['work_center_id']) ? (int) $data['work_center_id'] : null,
            machine_id:                     !empty($data['machine_id']) ? (int) $data['machine_id'] : null,
            setup_time_minutes:             isset($data['setup_time_minutes']) ? (float) $data['setup_time_minutes'] : 0.0,
            processing_time_minutes:        isset($data['processing_time_minutes']) ? (float) $data['processing_time_minutes'] : 0.0,
            wait_time_minutes:              isset($data['wait_time_minutes']) ? (float) $data['wait_time_minutes'] : 0.0,
            expected_yield_percentage:      isset($data['expected_yield_percentage']) ? (float) $data['expected_yield_percentage'] : 100.0,
            labor_cost_rate:                isset($data['labor_cost_rate']) ? (float) $data['labor_cost_rate'] : 0.0,
            machine_cost_rate:              isset($data['machine_cost_rate']) ? (float) $data['machine_cost_rate'] : 0.0,
            description:                    $data['description'] ?? null,
            instructions:                   $data['instructions'] ?? null,
            quality_required:               !empty($data['quality_required']),
            is_external:                    !empty($data['is_external']),
            vendor_id:                      !empty($data['vendor_id']) ? (int) $data['vendor_id'] : null,
            overlap_enabled:               filter_var($data['overlap_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            transfer_batch_quantity:        isset($data['transfer_batch_quantity']) ? (float) $data['transfer_batch_quantity'] : 0.0,
            transfer_lag_minutes:           isset($data['transfer_lag_minutes']) ? (int) $data['transfer_lag_minutes'] : 0,
            subcontract_lead_time_days:     isset($data['subcontract_lead_time_days']) ? (int) $data['subcontract_lead_time_days'] : 0,
            subcontract_cost_per_unit:      isset($data['subcontract_cost_per_unit']) ? (float) $data['subcontract_cost_per_unit'] : 0.0,
            subcontract_service_product_id: !empty($data['subcontract_service_product_id']) ? (int) $data['subcontract_service_product_id'] : null,
            material_supply_type:           $data['material_supply_type'] ?? (!empty($data['is_external']) ? 'company_supplied' : 'none'),
            dispatch_buffer_days:          isset($data['dispatch_buffer_days']) ? (int) $data['dispatch_buffer_days'] : 0,
            return_buffer_days:            isset($data['return_buffer_days']) ? (int) $data['return_buffer_days'] : 0,
        );
    }

    public function toArray(): array
    {
        return [
            'sequence'                       => $this->sequence,
            'operation_number'               => $this->operation_number,
            'name'                           => $this->name,
            'operation_type'                 => $this->operation_type,
            'work_center_id'                 => $this->work_center_id,
            'machine_id'                     => $this->machine_id,
            'setup_time_minutes'             => $this->setup_time_minutes,
            'processing_time_minutes'        => $this->processing_time_minutes,
            'wait_time_minutes'              => $this->wait_time_minutes,
            'expected_yield_percentage'      => $this->expected_yield_percentage,
            'labor_cost_rate'                => $this->labor_cost_rate,
            'machine_cost_rate'              => $this->machine_cost_rate,
            'description'                    => $this->description,
            'instructions'                   => $this->instructions,
            'quality_required'               => $this->quality_required,
            'is_external'                    => $this->is_external,
            'vendor_id'                      => $this->vendor_id,
            'overlap_enabled'               => $this->overlap_enabled,
            'transfer_batch_quantity'        => $this->transfer_batch_quantity,
            'transfer_lag_minutes'           => $this->transfer_lag_minutes,
            'subcontract_lead_time_days'     => $this->subcontract_lead_time_days,
            'subcontract_cost_per_unit'      => $this->subcontract_cost_per_unit,
            'subcontract_service_product_id' => $this->subcontract_service_product_id,
            'material_supply_type'           => $this->material_supply_type,
            'dispatch_buffer_days'           => $this->dispatch_buffer_days,
            'return_buffer_days'             => $this->return_buffer_days,
        ];
    }
}
