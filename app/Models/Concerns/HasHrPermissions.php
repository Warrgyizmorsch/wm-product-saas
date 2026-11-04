<?php

namespace App\Models\Concerns;

use App\Services\Access\AccessService;

/**
 * Authorization bridge for HRMS settings screens (org structure, salary
 * structures, leave policies, penalization rules) — administrative
 * configuration data, not scoped to an individual record's owner.
 */
trait HasHrPermissions
{
    public function hasHrPermission(string $permission): bool
    {
        return app(AccessService::class)->allows($this, $permission, [
            'tenant_id' => $this->tenant_id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'owner_id' => $this->id,
        ]);
    }
}
