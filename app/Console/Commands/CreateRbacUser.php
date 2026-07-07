<?php

namespace App\Console\Commands;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateRbacUser extends Command
{
    protected $signature = 'rbac:create-user
        {--tenant= : Tenant slug the user belongs to}
        {--role= : Role slug to assign}
        {--name= : Full name}
        {--email= : Login email}
        {--password= : Plain-text password (auto-generated if omitted)}';

    protected $description = 'Create a user for a tenant and assign them an RBAC role';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $role = $this->resolveRole($tenant);

        if ($role === null) {
            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email'],
        );

        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $password = $this->option('password');

        if (blank($password)) {
            $password = Str::password(16);
            $this->comment("Generated password: {$password}");
        }

        $user = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        UserRole::query()->updateOrCreate([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->info("Created user [{$user->email}] in tenant [{$tenant->slug}] with role [{$role->slug}].");

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $slug = $this->option('tenant') ?: $this->anticipate('Tenant slug', Tenant::query()->pluck('slug')->all());
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No tenant found with slug [{$slug}].");
        }

        return $tenant;
    }

    private function resolveRole(Tenant $tenant): ?Role
    {
        $rolesQuery = Role::query()->where(function ($query) use ($tenant) {
            $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        });

        $slug = $this->option('role') ?: $this->choice('Role', $rolesQuery->clone()->pluck('slug')->all());
        $role = $rolesQuery->clone()->where('slug', $slug)->first();

        if ($role === null) {
            $this->error("No role found with slug [{$slug}] visible to tenant [{$tenant->slug}].");
        }

        return $role;
    }
}
