<?php

namespace App\Domains\Access\Services;

use App\Domains\Platform\Services\UsageLimitService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly UsageLimitService $usageLimits,
        private readonly AccessAuditLogger $auditLogger,
    ) {
    }

    public function all(): Collection
    {
        return User::query()->with(['primaryRole', 'roles'])->orderBy('name')->get();
    }

    /**
     * Roles selectable in the form: global system roles plus this tenant's
     * own custom roles, minus super_admin unless the acting user already
     * holds it — an actor without super_admin must never be able to hand
     * that role to someone else through this screen.
     */
    public function assignableRoles(User $actor, int $tenantId): Collection
    {
        $query = Role::query()->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        });

        if (! $this->access->hasRole($actor, 'super_admin')) {
            $query->where('slug', '!=', 'super_admin');
        }

        return $query->orderBy('level')->get();
    }

    /**
     * @param array{role_ids: list<int>} $data
     */
    public function create(User $actor, array $data): User
    {
        $roleIds = $this->normalizeRoleIds($data['role_ids']);
        $this->guardRolesAssignable($actor, $roleIds);

        $tenant = tenant();
        if ($tenant !== null) {
            $this->usageLimits->assertCanAddUser($tenant);
        }

        return DB::transaction(function () use ($actor, $data, $roleIds) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $this->primaryRoleId($roleIds),
            ]);

            $this->syncRoles($user, $roleIds);

            $this->auditLogger->log(
                actor: $actor,
                action: 'user.roles.updated',
                tenantId: $user->tenant_id,
                targetUserId: $user->id,
                subjectType: 'user',
                subjectId: $user->id,
                before: ['role_ids' => []],
                after: ['role_ids' => $roleIds],
            );

            return $user;
        });
    }

    /**
     * @param array{role_ids: list<int>} $data
     */
    public function update(User $actor, User $user, array $data): void
    {
        $roleIds = $this->normalizeRoleIds($data['role_ids']);
        $this->guardRolesAssignable($actor, $roleIds);

        DB::transaction(function () use ($actor, $user, $data, $roleIds) {
            $previousRoleIds = UserRole::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->pluck('role_id')
                ->all();

            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $this->primaryRoleId($roleIds),
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);

            $this->syncRoles($user, $roleIds);

            sort($previousRoleIds);
            $sortedRoleIds = $roleIds;
            sort($sortedRoleIds);

            if ($previousRoleIds !== $sortedRoleIds) {
                $this->auditLogger->log(
                    actor: $actor,
                    action: 'user.roles.updated',
                    tenantId: $user->tenant_id,
                    targetUserId: $user->id,
                    subjectType: 'user',
                    subjectId: $user->id,
                    before: ['role_ids' => $previousRoleIds],
                    after: ['role_ids' => $roleIds],
                );
            }
        });
    }

    /**
     * @return list<int>
     */
    private function normalizeRoleIds(array $roleIds): array
    {
        return collect($roleIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The user's `role_id` FK is kept in sync as a single "primary" role —
     * other code (HRMS employee linking, login fallback, sidebar labels)
     * still reads it directly and isn't multi-role aware. The most senior
     * (lowest `level`) of the assigned roles is used, so the primary role
     * reflects the user's highest-privilege grant rather than an arbitrary
     * pick.
     */
    private function primaryRoleId(array $roleIds): int
    {
        return Role::query()
            ->whereIn('id', $roleIds)
            ->orderBy('level')
            ->value('id');
    }

    /**
     * A user can hold multiple roles at once — this replaces the full
     * user_roles set for this user+tenant with exactly the given roles
     * (create missing, delete removed), rather than the old single-role
     * behavior of deleting every other row on every assignment.
     *
     * @param list<int> $roleIds
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        UserRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->whereNotIn('role_id', $roleIds)
            ->delete();

        foreach ($roleIds as $roleId) {
            UserRole::query()->updateOrCreate([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'tenant_id' => $user->tenant_id,
            ]);
        }
    }

    /**
     * @param list<int> $roleIds
     */
    private function guardRolesAssignable(User $actor, array $roleIds): void
    {
        abort_if($roleIds === [], 422, 'At least one role must be assigned.');

        $roles = Role::query()->findMany($roleIds);

        abort_if($roles->count() !== count($roleIds), 404);

        abort_if(
            $roles->contains(fn (Role $role) => $role->slug === 'super_admin')
                && ! $this->access->hasRole($actor, 'super_admin'),
            403,
            'Only a super admin can assign the super admin role.'
        );
    }
}
