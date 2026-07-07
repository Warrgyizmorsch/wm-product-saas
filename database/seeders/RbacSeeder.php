<?php

namespace Database\Seeders;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles();

        $this->grant($roles['super_admin'], $permissions, RolePermission::SCOPE_PLATFORM);

        $this->grant($roles['tenant_owner'], $permissions, RolePermission::SCOPE_TENANT);
        $this->grant($roles['company_admin'], $permissions, RolePermission::SCOPE_TENANT);

        $this->grant($roles['production_manager'], [
            'production.work_center.manage' => $permissions['production.work_center.manage'],
            'production.machine.manage' => $permissions['production.machine.manage'],
            'production.routing.create' => $permissions['production.routing.create'],
            'production.routing.update' => $permissions['production.routing.update'],
            'production.routing.approve' => $permissions['production.routing.approve'],
            'production.routing.cancel' => $permissions['production.routing.cancel'],
            'production.bom.create' => $permissions['production.bom.create'],
            'production.bom.update' => $permissions['production.bom.update'],
            'production.bom.approve' => $permissions['production.bom.approve'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['production_engineer'], [
            'production.work_center.manage' => $permissions['production.work_center.manage'],
            'production.machine.manage' => $permissions['production.machine.manage'],
            'production.routing.create' => $permissions['production.routing.create'],
            'production.routing.update' => $permissions['production.routing.update'],
            'production.bom.create' => $permissions['production.bom.create'],
            'production.bom.update' => $permissions['production.bom.update'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['sales_manager'], [
            'crm.leads.view' => $permissions['crm.leads.view'],
            'crm.leads.create' => $permissions['crm.leads.create'],
            'crm.leads.update' => $permissions['crm.leads.update'],
            'crm.leads.delete' => $permissions['crm.leads.delete'],
            'crm.customers.view' => $permissions['crm.customers.view'],
            'crm.customers.create' => $permissions['crm.customers.create'],
            'crm.customers.update' => $permissions['crm.customers.update'],
            'crm.customers.delete' => $permissions['crm.customers.delete'],
            'crm.quotations.view' => $permissions['crm.quotations.view'],
            'crm.quotations.create' => $permissions['crm.quotations.create'],
            'crm.quotations.update' => $permissions['crm.quotations.update'],
            'crm.quotations.approve' => $permissions['crm.quotations.approve'],
            'crm.quotations.delete' => $permissions['crm.quotations.delete'],
        ], RolePermission::SCOPE_TENANT);

        // "create" abilities have no existing record to own yet, so they're granted at
        // tenant scope — the creator naturally becomes the owner of what they create.
        $this->grant($roles['sales_executive'], [
            'crm.leads.create' => $permissions['crm.leads.create'],
            'crm.quotations.create' => $permissions['crm.quotations.create'],
            'crm.customers.view' => $permissions['crm.customers.view'],
            'crm.customers.create' => $permissions['crm.customers.create'],
        ], RolePermission::SCOPE_TENANT);

        // "view"/"update" are restricted to records this user owns.
        $this->grant($roles['sales_executive'], [
            'crm.leads.view' => $permissions['crm.leads.view'],
            'crm.leads.update' => $permissions['crm.leads.update'],
            'crm.quotations.view' => $permissions['crm.quotations.view'],
        ], RolePermission::SCOPE_OWN);

        $this->assignDemoAdmin($roles['tenant_owner']);
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $definitions = [
            ['name' => 'platform.tenants.manage', 'module' => 'platform', 'entity' => 'tenants', 'action' => 'manage'],
            ['name' => 'access.roles.manage', 'module' => 'access', 'entity' => 'roles', 'action' => 'manage'],
            ['name' => 'access.permissions.manage', 'module' => 'access', 'entity' => 'permissions', 'action' => 'manage'],
            ['name' => 'production.work_center.manage', 'module' => 'production', 'entity' => 'work_center', 'action' => 'manage'],
            ['name' => 'production.machine.manage', 'module' => 'production', 'entity' => 'machine', 'action' => 'manage'],
            ['name' => 'production.routing.create', 'module' => 'production', 'entity' => 'routing', 'action' => 'create'],
            ['name' => 'production.routing.update', 'module' => 'production', 'entity' => 'routing', 'action' => 'update'],
            ['name' => 'production.routing.approve', 'module' => 'production', 'entity' => 'routing', 'action' => 'approve'],
            ['name' => 'production.routing.cancel', 'module' => 'production', 'entity' => 'routing', 'action' => 'cancel'],
            ['name' => 'production.bom.create', 'module' => 'production', 'entity' => 'bom', 'action' => 'create'],
            ['name' => 'production.bom.update', 'module' => 'production', 'entity' => 'bom', 'action' => 'update'],
            ['name' => 'production.bom.approve', 'module' => 'production', 'entity' => 'bom', 'action' => 'approve'],
            ['name' => 'audit.logs.view', 'module' => 'audit', 'entity' => 'logs', 'action' => 'view'],
            ['name' => 'crm.leads.view', 'module' => 'crm', 'entity' => 'leads', 'action' => 'view'],
            ['name' => 'crm.leads.create', 'module' => 'crm', 'entity' => 'leads', 'action' => 'create'],
            ['name' => 'crm.leads.update', 'module' => 'crm', 'entity' => 'leads', 'action' => 'update'],
            ['name' => 'crm.leads.delete', 'module' => 'crm', 'entity' => 'leads', 'action' => 'delete'],
            ['name' => 'crm.customers.view', 'module' => 'crm', 'entity' => 'customers', 'action' => 'view'],
            ['name' => 'crm.customers.create', 'module' => 'crm', 'entity' => 'customers', 'action' => 'create'],
            ['name' => 'crm.customers.update', 'module' => 'crm', 'entity' => 'customers', 'action' => 'update'],
            ['name' => 'crm.customers.delete', 'module' => 'crm', 'entity' => 'customers', 'action' => 'delete'],
            ['name' => 'crm.quotations.view', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'view'],
            ['name' => 'crm.quotations.create', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'create'],
            ['name' => 'crm.quotations.update', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'update'],
            ['name' => 'crm.quotations.approve', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'approve'],
            ['name' => 'crm.quotations.delete', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'delete'],
        ];

        $permissions = [];

        foreach ($definitions as $definition) {
            $permissions[$definition['name']] = Permission::query()->updateOrCreate(
                ['name' => $definition['name']],
                $definition + ['is_system' => true],
            );
        }

        return $permissions;
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $definitions = [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'tenant_id' => null, 'level' => 1],
            ['slug' => 'tenant_owner', 'name' => 'Tenant Owner', 'tenant_id' => null, 'level' => 10],
            ['slug' => 'company_admin', 'name' => 'Company Admin', 'tenant_id' => null, 'level' => 20],
            ['slug' => 'production_manager', 'name' => 'Production Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'production_engineer', 'name' => 'Production Engineer', 'tenant_id' => null, 'level' => 50],
            ['slug' => 'sales_manager', 'name' => 'Sales Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'sales_executive', 'name' => 'Sales Executive', 'tenant_id' => null, 'level' => 50],
            ['slug' => 'auditor', 'name' => 'Auditor', 'tenant_id' => null, 'level' => 80],
            ['slug' => 'read_only', 'name' => 'Read Only User', 'tenant_id' => null, 'level' => 90],
        ];

        $roles = [];

        foreach ($definitions as $definition) {
            $roles[$definition['slug']] = Role::query()->updateOrCreate(
                [
                    'tenant_id' => $definition['tenant_id'],
                    'slug' => $definition['slug'],
                ],
                $definition + ['is_system' => true],
            );
        }

        return $roles;
    }

    /**
     * @param array<string, Permission> $permissions
     */
    private function grant(Role $role, array $permissions, string $scope): void
    {
        foreach ($permissions as $permission) {
            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'scope' => $scope,
                ],
            );
        }
    }

    private function assignDemoAdmin(Role $role): void
    {
        $tenant = Tenant::query()->where('slug', 'demo')->first();
        $user = User::query()
            ->where('email', 'admin@example.com')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->first();

        if ($tenant === null || $user === null) {
            return;
        }

        UserRole::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'tenant_id' => $tenant->id,
            ],
        );

        $user->forceFill(['role_id' => $role->id])->save();
    }
}
