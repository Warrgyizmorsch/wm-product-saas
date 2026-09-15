<?php

namespace App\Core\Tenant;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;

/**
 * Every tenant needs one company and one branch before any module can store
 * company/branch-scoped master data. Reuses what already exists, so running it
 * again (or on a tenant that was set up by hand) never adds a second company.
 *
 * Must run inside TenantRunner::run() for the tenant.
 */
class DefaultOrganization
{
    /**
     * @return array{0: Company, 1: Branch}
     */
    public function ensure(Tenant $tenant): array
    {
        $company = Company::query()->orderByDesc('is_default')->orderBy('id')->first();

        if ($company === null) {
            $company = new Company([
                'company_name' => $tenant->settings['display_name'] ?? $tenant->name,
                'currency' => $tenant->getAttribute('currency') ?? $tenant->settings['currency'] ?? 'INR',
                'timezone' => $tenant->timezone ?: config('app.timezone'),
                'status' => true,
            ]);
            $company->forceFill(['tenant_id' => $tenant->id, 'is_default' => true])->save();
        }

        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($branch === null) {
            $businessUnit = BusinessUnit::query()->where('company_id', $company->id)->orderBy('id')->first();

            if ($businessUnit === null) {
                $businessUnit = new BusinessUnit(['company_id' => $company->id, 'name' => 'Head Office', 'code' => 'HO', 'status' => true]);
                $businessUnit->forceFill(['tenant_id' => $tenant->id])->save();
            }

            $branch = new Branch([
                'company_id' => $company->id,
                'business_unit_id' => $businessUnit->id,
                'name' => $tenant->settings['branch'] ?? 'Main Office',
                'code' => 'MAIN',
                'status' => true,
            ]);
            $branch->forceFill(['tenant_id' => $tenant->id, 'is_default' => true])->save();
        }

        return [$company, $branch];
    }
}
