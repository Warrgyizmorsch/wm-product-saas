<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maker-checker for manual journals and vouchers: a journal can wait in
 * 'pending_approval' until a second person approves (→ posted) or rejects it.
 * posted_by keeps meaning "who entered it" (the maker); these columns record
 * the checker. Pending and rejected journals never reach the ledger — every
 * report reads only posted/reversed journals.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'accounting.journals.approve' => ['entity' => 'journals', 'action' => 'approve'],
        'accounting.approvals.configure' => ['entity' => 'approvals', 'action' => 'configure'],
    ];

    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason', 500)->nullable()->after('rejected_at');
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['approved_at', 'rejected_at', 'rejection_reason']);
        });

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Existing installs don't re-run RbacSeeder, so add the permissions here.
     * Only owner/admin roles approve — not 'accountant', who is the usual maker.
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
