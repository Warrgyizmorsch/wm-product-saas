<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Existing installs don't re-run RbacSeeder: add the /admin console permissions,
 * the platform staff roles and their grants here (mirrors RbacSeeder).
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'platform.console.access' => ['console', 'access'],
        'platform.leads.manage' => ['leads', 'manage'],
        'platform.tenants.view' => ['tenants', 'view'],
        'platform.tenants.impersonate' => ['tenants', 'impersonate'],
        'platform.tenants.delete' => ['tenants', 'delete'],
        'platform.subscriptions.manage' => ['subscriptions', 'manage'],
        'platform.payments.view' => ['payments', 'view'],
        'platform.payments.refund' => ['payments', 'refund'],
        'platform.support.manage' => ['support', 'manage'],
        'platform.settings.manage' => ['settings', 'manage'],
        'platform.audit.view' => ['audit', 'view'],
    ];

    private const STAFF_ROLES = [
        'platform_sales' => ['Platform Sales', ['platform.console.access', 'platform.leads.manage', 'platform.tenants.view']],
        'platform_support' => ['Platform Support', ['platform.console.access', 'platform.tenants.view', 'platform.usage.view', 'platform.support.manage']],
        'platform_finance' => ['Platform Finance', ['platform.console.access', 'platform.tenants.view', 'platform.usage.view', 'platform.subscriptions.manage', 'platform.payments.view', 'platform.payments.refund']],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name => [$entity, $action]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'platform', 'entity' => $entity, 'action' => $action, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->where('name', 'like', 'platform.%')->pluck('id', 'name');

        $superAdmin = DB::table('roles')->whereNull('tenant_id')->where('slug', 'super_admin')->value('id');
        if ($superAdmin) {
            foreach (self::PERMISSIONS as $name => $_) {
                $this->grant($superAdmin, $permissionIds[$name], $now);
            }
        }

        foreach (self::STAFF_ROLES as $slug => [$label, $names]) {
            DB::table('roles')->updateOrInsert(
                ['tenant_id' => null, 'slug' => $slug],
                ['name' => $label, 'level' => 2, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $roleId = DB::table('roles')->whereNull('tenant_id')->where('slug', $slug)->value('id');

            foreach ($names as $name) {
                if (isset($permissionIds[$name])) {
                    $this->grant($roleId, $permissionIds[$name], $now);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleIds = DB::table('roles')->whereNull('tenant_id')->whereIn('slug', array_keys(self::STAFF_ROLES))->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');

        DB::table('role_permissions')->whereIn('role_id', $roleIds)->orWhereIn('permission_id', $permissionIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    private function grant(int $roleId, int $permissionId, $now): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role_id' => $roleId, 'permission_id' => $permissionId, 'scope' => 'platform'],
            ['created_at' => $now, 'updated_at' => $now],
        );
    }
};
