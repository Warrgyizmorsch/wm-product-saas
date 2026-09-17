<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the platform administrator named by PLATFORM_ADMIN_EMAIL /
 * PLATFORM_ADMIN_PASSWORD (config/tenancy.php). Skipped when either is unset,
 * so no default password is ever seeded; an existing admin's password is
 * never overwritten.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('tenancy.platform_admin', []);
        $email = $config['email'] ?? null;
        $password = $config['password'] ?? null;

        if (blank($email) || blank($password)) {
            $this->command?->warn('Skipping platform admin: set PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD to create one.');

            return;
        }

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->first();

        if ($role === null) {
            $this->command?->warn('Skipping platform admin: the super_admin role does not exist (run RbacSeeder first).');

            return;
        }

        $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();

        if ($user !== null && $user->tenant_id !== null) {
            $this->command?->warn("Skipping platform admin: {$email} already belongs to a tenant.");

            return;
        }

        $user ??= new User(['email' => $email, 'password' => $password]);
        $user->forceFill([
            'tenant_id' => null,
            'name' => $config['name'] ?? null ?: 'Platform Admin',
            'role_id' => $role->id,
        ])->save();
    }
}
