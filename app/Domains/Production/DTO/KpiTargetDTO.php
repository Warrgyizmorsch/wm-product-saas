<?php

namespace App\Domains\Production\DTO;

class KpiTargetDTO
{
    public function __construct(
        public readonly string $kpi_name,
        public readonly float $target_value
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            kpi_name:     $data['kpi_name'],
            target_value: (float) $data['target_value']
        );
    }
}
