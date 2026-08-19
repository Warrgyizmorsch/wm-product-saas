<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\BiometricDevice;

interface BiometricDeviceRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeDevice(array $validated): BiometricDevice;

    public function updateDevice(BiometricDevice $device, array $validated): bool;

    public function deleteDevice(BiometricDevice $device): bool;
}
