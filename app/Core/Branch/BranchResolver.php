<?php

namespace App\Core\Branch;

use App\Domains\HRMS\Models\Branch;
use Illuminate\Http\Request;

class BranchResolver
{
    public function resolve(Request $request, int $companyId): ?Branch
    {
        $explicitBranchId = $request->header('X-Branch-Id') ?: ($request->header('X-Branch') ?: $request->input('branch_id'));
        if ($explicitBranchId) {
            $branch = Branch::query()->where('company_id', $companyId)->find($explicitBranchId);
            if ($branch !== null) {
                return $branch;
            }
        }

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

        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
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
