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

        return new self(
            section_id:         $sectionId,
            name:               $data['name'] ?? '',
            code:               strtoupper(trim($data['code'] ?? '')),
            status:             $data['status'] ?? 'active',
            machine_type:       $data['machine_type'] ?? null,
            manufacturer:       $data['manufacturer'] ?? null,
            model_number:       $data['model_number'] ?? null,
            capacity:           isset($data['capacity']) && $data['capacity'] !== '' ? (float) $data['capacity'] : null,
            installation_date:  $data['installation_date'] ?? null,
            maintenance_status: $data['maintenance_status'] ?? null,
            has_section_id:     $hasSectionId,
        );
    }

    public function toArray(): array
    {
        return [
            'section_id'         => $this->section_id,
            'name'               => $this->name,
            'code'               => $this->code,
            'status'             => $this->status,
            'machine_type'       => $this->machine_type,
            'manufacturer'       => $this->manufacturer,
            'model_number'       => $this->model_number,
            'capacity'           => $this->capacity,
            'installation_date'  => $this->installation_date,
            'maintenance_status' => $this->maintenance_status,
        ];
    }
}
