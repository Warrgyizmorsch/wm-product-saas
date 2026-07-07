<?php

namespace App\Domains\Access\Services;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function all(): Collection
    {
        return User::query()->with('primaryRole')->orderBy('name')->get();
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

    public function create(User $actor, array $data): User
    {
        $this->guardRoleAssignable($actor, (int) $data['role_id']);

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role_id' => $data['role_id'],
            ]);

            $this->syncRole($user, (int) $data['role_id']);

            return $user;
        });
    }

    public function update(User $actor, User $user, array $data): void
    {
        $this->guardRoleAssignable($actor, (int) $data['role_id']);

        DB::transaction(function () use ($user, $data) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            $user->update($payload);

            $this->syncRole($user, (int) $data['role_id']);
        });
    }

    private function syncRole(User $user, int $roleId): void
    {
        UserRole::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('role_id', '!=', $roleId)
            ->delete();

        UserRole::query()->updateOrCreate([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'tenant_id' => $user->tenant_id,
        ]);
    }

    private function guardRoleAssignable(User $actor, int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        abort_if(
            $role->slug === 'super_admin' && ! $this->access->hasRole($actor, 'super_admin'),
            403,
            'Only a super admin can assign the super admin role.'
        );
    }
}
