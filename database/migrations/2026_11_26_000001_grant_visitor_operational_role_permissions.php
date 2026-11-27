<?php

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $operationalPermNames = [
            'visitor.visitors.view',
            'visitor.visitors.create',
            'visitor.visitors.update',
            'visitor.passes.view',
            'visitor.passes.create',
            'visitor.passes.update',
            'visitor.passes.checkin',
            'visitor.passes.checkout',
        ];

        $operationalPermIds = Permission::whereIn('name', $operationalPermNames)->pluck('id')->all();
        if (empty($operationalPermIds)) {
            return;
        }

        $operationalRoles = Role::whereIn('slug', ['receptionist', 'security', 'front_desk', 'gate_keeper'])->get();
        foreach ($operationalRoles as $role) {
            foreach ($operationalPermIds as $pId) {
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
        // Additive grant migration; keep existing operational grants intact on rollback.
    }
};
