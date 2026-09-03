<?php

namespace App\Core\Branch;

use App\Domains\HRMS\Models\Branch;
use Illuminate\Http\Request;

class BranchResolver
{
    public function resolve(Request $request, int $companyId): ?Branch
    {
        $sessionBranchId = $request->hasSession() ? $request->session()->get('branch_id') : null;

        if ($sessionBranchId) {
            $branch = Branch::query()
                ->where('company_id', $companyId)
                ->find($sessionBranchId);

            if ($branch !== null) {
                return $branch;
            }
        }

        $user = $request->user();

        if ($user !== null && $user->branch_id) {
            $branch = Branch::query()
                ->where('company_id', $companyId)
                ->find($user->branch_id);

            if ($branch !== null) {
                return $branch;
            }
        }

        $default = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first();

        if ($default !== null) {
            return $default;
        }

        return Branch::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->first();
    }
}
