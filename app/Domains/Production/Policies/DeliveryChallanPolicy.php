<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\DeliveryChallan;
use App\Models\User;
use App\Services\Access\AccessService;

class DeliveryChallanPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, DeliveryChallan $challan): bool
    {
        if ($challan->tenant_id !== $user->tenant_id) {
            return false;
        }

        $activeCompanyId = company_id() ?? (class_exists(\App\Core\Company\CompanyContext::class) ? app(\App\Core\Company\CompanyContext::class)->id() : null) ?? $user->company_id;
        if ($activeCompanyId !== null && $challan->company_id !== null && (int) $challan->company_id !== (int) $activeCompanyId) {
            return false;
        }

        $activeBranchId = branch_id() ?? (class_exists(\App\Core\Branch\BranchContext::class) ? app(\App\Core\Branch\BranchContext::class)->id() : null) ?? $user->branch_id;
        if ($activeBranchId !== null && $challan->branch_id !== null && (int) $challan->branch_id !== (int) $activeBranchId) {
            return false;
        }

        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $challan->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function dispatch(User $user, DeliveryChallan $challan): bool
    {
        if ($challan->tenant_id !== $user->tenant_id || $challan->status === 'dispatched') {
            return false;
        }

        $activeCompanyId = company_id() ?? (class_exists(\App\Core\Company\CompanyContext::class) ? app(\App\Core\Company\CompanyContext::class)->id() : null) ?? $user->company_id;
        if ($activeCompanyId !== null && $challan->company_id !== null && (int) $challan->company_id !== (int) $activeCompanyId) {
            return false;
        }

        $activeBranchId = branch_id() ?? (class_exists(\App\Core\Branch\BranchContext::class) ? app(\App\Core\Branch\BranchContext::class)->id() : null) ?? $user->branch_id;
        if ($activeBranchId !== null && $challan->branch_id !== null && (int) $challan->branch_id !== (int) $activeBranchId) {
            return false;
        }

        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $challan->tenant_id,
        ]);
    }

    public function receive(User $user, DeliveryChallan $challan): bool
    {
        if ($challan->tenant_id !== $user->tenant_id || $challan->status === 'completed') {
            return false;
        }

        $activeCompanyId = company_id() ?? (class_exists(\App\Core\Company\CompanyContext::class) ? app(\App\Core\Company\CompanyContext::class)->id() : null) ?? $user->company_id;
        if ($activeCompanyId !== null && $challan->company_id !== null && (int) $challan->company_id !== (int) $activeCompanyId) {
            return false;
        }

        $activeBranchId = branch_id() ?? (class_exists(\App\Core\Branch\BranchContext::class) ? app(\App\Core\Branch\BranchContext::class)->id() : null) ?? $user->branch_id;
        if ($activeBranchId !== null && $challan->branch_id !== null && (int) $challan->branch_id !== (int) $activeBranchId) {
            return false;
        }

        return $this->access->allows($user, 'production.mes.execute', [
            'tenant_id' => $challan->tenant_id,
        ]);
    }
}
