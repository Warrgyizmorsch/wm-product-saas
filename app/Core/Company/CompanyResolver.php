<?php

namespace App\Core\Company;

use App\Domains\HRMS\Models\Company;
use Illuminate\Http\Request;

class CompanyResolver
{
    public function resolve(Request $request, int $tenantId): ?Company
    {
        $sessionCompanyId = $request->hasSession() ? $request->session()->get('company_id') : null;

        if ($sessionCompanyId) {
            $company = Company::query()
                ->where('tenant_id', $tenantId)
                ->find($sessionCompanyId);

            if ($company !== null) {
                return $company;
            }
        }

        $user = $request->user();

        if ($user !== null && $user->company_id) {
            $company = Company::query()
                ->where('tenant_id', $tenantId)
                ->find($user->company_id);

            if ($company !== null) {
                return $company;
            }
        }

        $default = Company::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if ($default !== null) {
            return $default;
        }

        return Company::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->first();
    }
}
