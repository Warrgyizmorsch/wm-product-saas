<?php

namespace App\Domains\HRMS\Traits;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait to allow HRMS Eloquent models to easily scope queries based on the actor's organizational boundaries.
 */
trait HasHrmsScope
{
    /**
     * Scope query to only include records accessible by the given actor (User or Employee).
     * Automatically falls back to the authenticated user if actor is null.
     */
    public function scopeForUserScope(Builder $query, User|Employee|null $actor = null, ?string $employeeIdColumn = null): Builder
    {
        $scopeService = app(HrmsScopeService::class);
        $column = $employeeIdColumn ?? ($this instanceof Employee ? 'id' : 'employee_id');

        return $scopeService->applyRelatedScope($query, $actor, $column);
    }
}
