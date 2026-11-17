<?php

namespace App\Domains\Production\DTO;

class MachineDTO
{
    public function __construct(
        public readonly ?int    $section_id = null,
        public readonly string  $name = '',
        public readonly string  $code = '',
        public readonly string  $status = 'active',
        public readonly ?string $machine_type = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $model_number = null,
        public readonly ?float  $capacity = null,
        public readonly ?string $energy_source = 'electricity',
        public readonly ?float  $consumption_per_hour = 0.0,
        public readonly ?float  $energy_rate_per_unit = 0.0,
        public readonly ?float  $operating_cost_per_hour = 0.0,
        public readonly ?string $installation_date = null,
        public readonly ?string $maintenance_status = null,
        public readonly bool    $has_section_id = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $hasSectionId = array_key_exists('section_id', $data);
        $sectionId = $hasSectionId && $data['section_id'] !== '' && $data['section_id'] !== null
            ? (int) $data['section_id']
            : null;

        $energySource = $data['energy_source'] ?? 'electricity';
        $consumption  = isset($data['consumption_per_hour']) && $data['consumption_per_hour'] !== '' ? (float) $data['consumption_per_hour'] : 0.0;
        $rate         = isset($data['energy_rate_per_unit']) && $data['energy_rate_per_unit'] !== '' ? (float) $data['energy_rate_per_unit'] : 0.0;
        $operatingCost = isset($data['operating_cost_per_hour']) && $data['operating_cost_per_hour'] !== '' ? (float) $data['operating_cost_per_hour'] : 0.0;

        if ($operatingCost <= 0.0 && in_array($energySource, ['electricity', 'petrol', 'diesel'], true)) {
            $operatingCost = round($consumption * $rate, 4);
        }

        return new self(
            section_id:              $sectionId,
            name:                    $data['name'] ?? '',
            code:                    strtoupper(trim($data['code'] ?? '')),
            status:                  $data['status'] ?? 'active',
            machine_type:            $data['machine_type'] ?? null,
            manufacturer:            $data['manufacturer'] ?? null,
            model_number:            $data['model_number'] ?? null,
            capacity:                isset($data['capacity']) && $data['capacity'] !== '' ? (float) $data['capacity'] : null,
            energy_source:           $energySource,
            consumption_per_hour:    $consumption,
            energy_rate_per_unit:    $rate,
            operating_cost_per_hour: $operatingCost,
            installation_date:       $data['installation_date'] ?? null,
            maintenance_status:      $data['maintenance_status'] ?? null,
            has_section_id:          $hasSectionId,
        );
    }

    public function toArray(): array
    {
        return [
            'section_id'              => $this->section_id,
            'name'                    => $this->name,
            'code'                    => $this->code,
            'status'                  => $this->status,
            'machine_type'            => $this->machine_type,
            'manufacturer'            => $this->manufacturer,
            'model_number'            => $this->model_number,
            'capacity'                => $this->capacity,
            'energy_source'           => $this->energy_source,
            'consumption_per_hour'    => $this->consumption_per_hour,
            'energy_rate_per_unit'    => $this->energy_rate_per_unit,
            'operating_cost_per_hour' => $this->operating_cost_per_hour,
            'installation_date'       => $this->installation_date,
            'maintenance_status'      => $this->maintenance_status,
        ];
    }
}
