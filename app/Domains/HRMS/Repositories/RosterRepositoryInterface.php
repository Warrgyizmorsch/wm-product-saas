<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\Production\Models\ProductionShift;
use Illuminate\Http\Request;

interface RosterRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeShift(array $validated): ProductionShift;

    public function updateShift(ProductionShift $shift, array $validated): bool;

    public function deleteShift(ProductionShift $shift): bool;

    public function assignShiftRoster(array $validated): bool;

    public function clearShiftRoster(array $validated): bool;
}
