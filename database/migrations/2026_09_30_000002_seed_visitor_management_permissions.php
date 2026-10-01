<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsData = [
            ['name' => 'visitor.visitors.view', 'module' => 'visitor', 'entity' => 'visitors', 'action' => 'view', 'description' => 'View visitors directory and profiles'],
            ['name' => 'visitor.visitors.create', 'module' => 'visitor', 'entity' => 'visitors', 'action' => 'create', 'description' => 'Register new visitors'],
            ['name' => 'visitor.visitors.update', 'module' => 'visitor', 'entity' => 'visitors', 'action' => 'update', 'description' => 'Update visitor information'],
            ['name' => 'visitor.visitors.delete', 'module' => 'visitor', 'entity' => 'visitors', 'action' => 'delete', 'description' => 'Delete visitor records'],

            ['name' => 'visitor.passes.view', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'view', 'description' => 'View visitor passes and logs'],
            ['name' => 'visitor.passes.create', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'create', 'description' => 'Issue new visitor gate passes'],
            ['name' => 'visitor.passes.update', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'update', 'description' => 'Edit visitor gate passes'],
            ['name' => 'visitor.passes.delete', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'delete', 'description' => 'Cancel or delete visitor passes'],
            ['name' => 'visitor.passes.checkin', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'checkin', 'description' => 'Check in visitors at gate'],
            ['name' => 'visitor.passes.checkout', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'checkout', 'description' => 'Check out visitors at gate'],
            ['name' => 'visitor.passes.approve', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'approve', 'description' => 'Approve or pre-authorize visitor requests'],
            ['name' => 'visitor.passes.reject', 'module' => 'visitor', 'entity' => 'passes', 'action' => 'reject', 'description' => 'Reject visitor entry requests'],

            ['name' => 'visitor.settings.manage', 'module' => 'visitor', 'entity' => 'settings', 'action' => 'manage', 'description' => 'Manage visitor module settings & gate masters'],
        ];

        $permissionIds = [];
        foreach ($permissionsData as $data) {
            $perm = Permission::firstOrCreate(
                ['name' => $data['name']],
                [
                    'module'      => $data['module'],
                    'entity'      => $data['entity'],
                    'action'      => $data['action'],
                    'description' => $data['description'],
                    'is_system'   => true,
                ]
            );
            $permissionIds[] = $perm->id;
        }

        // Grant to super_admin (platform scope)
        $superAdminRoles = Role::whereIn('slug', ['super_admin', 'admin'])->get();
        foreach ($superAdminRoles as $role) {
            foreach ($permissionIds as $pId) {
                RolePermission::firstOrCreate([
                    'role_id'       => $role->id,
                    'permission_id' => $pId,
                    'scope'         => RolePermission::SCOPE_PLATFORM,
                ]);
            }
        }

        // Grant to tenant_owner & company_admin (tenant scope)
        $adminRoles = Role::whereIn('slug', ['tenant_owner', 'company_admin'])->get();
        foreach ($adminRoles as $role) {
            foreach ($permissionIds as $pId) {
                RolePermission::firstOrCreate([
                    'role_id'       => $role->id,
                    'permission_id' => $pId,
                    'scope'         => RolePermission::SCOPE_TENANT,
                ]);
            }
        }
    }

    public function down(): void
    {
        $names = [
            'visitor.visitors.view',
            'visitor.visitors.create',
            'visitor.visitors.update',
            'visitor.visitors.delete',
            'visitor.passes.view',
            'visitor.passes.create',
            'visitor.passes.update',
            'visitor.passes.delete',
            'visitor.passes.checkin',
            'visitor.passes.checkout',
            'visitor.passes.approve',
            'visitor.passes.reject',
            'visitor.settings.manage',
        ];

        $perms = Permission::whereIn('name', $names)->get();
        RolePermission::whereIn('permission_id', $perms->pluck('id'))->delete();
        Permission::whereIn('name', $names)->delete();
    }
};
