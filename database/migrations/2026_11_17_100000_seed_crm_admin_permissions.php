<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Dedicated permissions for CRM admin screens that used to be authorised by
 * "crm.leads.create" / "crm.deals.create" (which every sales executive holds):
 * tenant CRM settings, the public web-to-lead form, and lead-status / deal-stage
 * masters.
 */
return new class extends Migration
{
    /** permission name => [description, roles granted at tenant scope] */
    private const PERMISSIONS = [
        'crm.settings.manage' => ['Manage CRM settings (lead assignment, quotation approval policy, field visits)', ['tenant_owner', 'company_admin']],
        'crm.webforms.manage' => ['Manage the public web-to-lead form', ['tenant_owner', 'company_admin', 'sales_manager']],
        'crm.masters.manage' => ['Manage lead statuses and deal stages', ['tenant_owner', 'company_admin', 'sales_manager']],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name => [$description, $roleSlugs]) {
            [$module, $entity, $action] = explode('.', $name);

            $permission = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'module' => $module,
                    'entity' => $entity,
                    'action' => $action,
                    'description' => $description,
                    'is_system' => true,
                ]
            );

            foreach (Role::whereIn('slug', ['super_admin'])->get() as $role) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'scope' => RolePermission::SCOPE_PLATFORM,
                ]);
            }

            foreach (Role::whereIn('slug', $roleSlugs)->get() as $role) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'scope' => RolePermission::SCOPE_TENANT,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = Permission::whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        RolePermission::whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();
    }
};
