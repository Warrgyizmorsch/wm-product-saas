<?php

namespace App\Domains\Production\Policies;

use App\Domains\Production\Models\ProductionSchedule;
use App\Models\User;

class ProductionSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionSchedule $schedule): bool
    {
        return $schedule->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasProductionPermission('production.schedule.manage');
    }

    public function delete(User $user, ProductionSchedule $schedule): bool
    {
        return $user->hasProductionPermission('production.schedule.manage', $schedule->tenant_id);
    }

    public function release(User $user, ProductionSchedule $schedule): bool
    {
        return $user->hasProductionPermission('production.schedule.manage', $schedule->tenant_id);
    }

    public function cancel(User $user, ProductionSchedule $schedule): bool
    {
        return $user->hasProductionPermission('production.schedule.manage', $schedule->tenant_id);
    }
}
