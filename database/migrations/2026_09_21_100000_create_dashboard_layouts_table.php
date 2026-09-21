<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'dashboard.tenant.manage';

    public function up(): void
    {
        // user_id set = personal layout; role_id set = role default; both null = tenant default.
        Schema::create('dashboard_layouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->json('widgets');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'role_id'], 'dashboard_layouts_scope_unique');
        });

        $this->grantManagePermission();
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }

    /** Existing installs don't re-run RbacSeeder, so add the permission and its owner grants here. */
    private function grantManagePermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION],
            ['module' => 'dashboard', 'entity' => 'tenant', 'action' => 'manage', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        );
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        foreach (DB::table('roles')->whereIn('slug', ['tenant_owner', 'company_admin'])->pluck('id') as $roleId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId, 'scope' => 'tenant'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
