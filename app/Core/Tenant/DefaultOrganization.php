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
            $companyName = trim((string) $tenant->name);
            $displayName = isset($tenant->settings['display_name']) ? trim((string) $tenant->settings['display_name']) : '';
            // Display name matches legal entity name; if empty, fallback to company name
            $legalName = $displayName !== '' ? $displayName : $companyName;

            $company = new Company([
                'company_name' => $companyName,
                'legal_name' => $legalName,
                'email' => $tenant->billing_email ?? null,
                'logo' => $tenant->settings['logo_full'] ?? null,
                'currency' => $tenant->getAttribute('currency') ?? $tenant->settings['currency'] ?? 'INR',
                'timezone' => $tenant->timezone ?: config('app.timezone'),
                'status' => true,
            ]);
            $company->forceFill(['tenant_id' => $tenant->id, 'is_default' => true])->save();
        } else {
            if (empty($company->legal_name)) {
                $displayName = isset($tenant->settings['display_name']) ? trim((string) $tenant->settings['display_name']) : '';
                $company->forceFill([
                    'legal_name' => $displayName !== '' ? $displayName : $company->company_name,
                ])->save();
            }
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

            $branchName = !empty($tenant->settings['branch']) ? trim((string) $tenant->settings['branch']) : 'Main Office';
            $cleanedCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $branchName));
            $branchCode = !empty($cleanedCode) ? substr($cleanedCode, 0, 4) : 'MAIN';

            $branch = new Branch([
                'company_id' => $company->id,
                'business_unit_id' => $businessUnit->id,
                'name' => $branchName,
                'code' => $branchCode,
                'status' => true,
            ]);
            $branch->forceFill(['tenant_id' => $tenant->id, 'is_default' => true])->save();
        }

        return [$company, $branch];
    }
}
