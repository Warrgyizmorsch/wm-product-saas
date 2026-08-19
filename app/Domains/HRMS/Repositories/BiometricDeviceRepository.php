<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\BiometricDevice;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;

class BiometricDeviceRepository implements BiometricDeviceRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $search = trim((string) ($inputs['search'] ?? ''));
        $selectedCompanyId = !empty($inputs['company_id']) ? (int) $inputs['company_id'] : null;
        $selectedBusinessUnitId = !empty($inputs['business_unit_id']) ? (int) $inputs['business_unit_id'] : null;
        $selectedBranchId = !empty($inputs['branch_id']) ? (int) $inputs['branch_id'] : null;
        $sort = !empty($inputs['sort']) ? (string) $inputs['sort'] : 'name_asc';

        $query = BiometricDevice::with(['company', 'businessUnit', 'branch']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('device_serial', 'like', "%{$search}%");
            });
        }

        if ($selectedCompanyId) {
            $query->where('company_id', $selectedCompanyId);
        }
        if ($selectedBusinessUnitId) {
            $query->where('business_unit_id', $selectedBusinessUnitId);
        }
        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        switch ($sort) {
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'serial_asc':
                $query->orderBy('device_serial', 'asc');
                break;
            case 'serial_desc':
                $query->orderBy('device_serial', 'desc');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name', 'asc');
                break;
        }

        $devices = $query->paginate(10)->withQueryString();

        $companies = Company::where('status', true)->get();
        $businessUnits = BusinessUnit::where('status', true)->get();
        $branches = Branch::where('status', true)->get();

        return compact(
            'devices',
            'companies',
            'businessUnits',
            'branches',
            'selectedCompanyId',
            'selectedBusinessUnitId',
            'selectedBranchId',
            'search',
            'sort'
        );
    }

    public function storeDevice(array $validated): BiometricDevice
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['tenant_id'] = $tenantId;

        return BiometricDevice::create($validated);
    }

    public function updateDevice(BiometricDevice $device, array $validated): bool
    {
        return $device->update($validated);
    }

    public function deleteDevice(BiometricDevice $device): bool
    {
        return $device->delete();
    }
}
