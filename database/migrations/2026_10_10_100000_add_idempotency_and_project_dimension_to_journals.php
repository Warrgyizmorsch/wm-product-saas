<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Closes four gaps against the accounting spec:
 *
 * - journals.idempotency_key: auto-postings (invoice, bill, payment, payroll…)
 *   set "{reference_type}:{reference_id}" and the unique index makes "post this
 *   document once" hold even when two queue workers race. Reversing a journal
 *   clears its key, so the document can be posted again after a reversal.
 * - journal_entries.project_id: the optional Project dimension on a line, next
 *   to cost center and branch.
 * - accounting.periods.reopen / accounting.fiscal_years.reopen: reopening closed
 *   books is now its own permission instead of riding on close/manage.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'accounting.periods.reopen' => ['entity' => 'periods', 'action' => 'reopen'],
        'accounting.fiscal_years.reopen' => ['entity' => 'fiscal_years', 'action' => 'reopen'],
    ];

    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('reference_id');
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('cost_center_id')->constrained('projects')->nullOnDelete();
            $table->index(['tenant_id', 'project_id']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'project_id']);
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Existing installs don't re-run RbacSeeder. Same holders as close/manage
     * today (owner/admin), so nobody loses access; tenants can now grant
     * closing without reopening.
     */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name => $definition) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'accounting'] + $definition + ['is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            foreach (DB::table('roles')->whereIn('slug', ['tenant_owner', 'company_admin'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId, 'scope' => 'tenant'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }

            $superAdmin = DB::table('roles')->where('slug', 'super_admin')->value('id');
            if ($superAdmin) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $superAdmin, 'permission_id' => $permissionId, 'scope' => 'platform'],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }
};
