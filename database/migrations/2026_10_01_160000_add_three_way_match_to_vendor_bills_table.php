<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 3-way match (PO ↔ GRN ↔ vendor bill). Each bill records how its lines
 * compared with what was ordered and received; in "hold" mode a bill with
 * exceptions is saved as 'On Hold' — not posted to the ledger and not
 * payable — until someone releases it with a reason.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'purchase.bills.release_hold' => 'release_hold',
        'purchase.bills.match_configure' => 'match_configure',
    ];

    public function up(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            // matched | exception | not_applicable — null for bills created before matching existed
            $table->string('match_status', 20)->nullable()->after('status');
            $table->json('match_details')->nullable()->after('match_status');
            $table->dateTime('match_checked_at')->nullable()->after('match_details');
            $table->foreignId('hold_released_by')->nullable()->after('match_checked_at')->constrained('users')->nullOnDelete();
            $table->dateTime('hold_released_at')->nullable()->after('hold_released_by');
            $table->string('hold_release_reason', 500)->nullable()->after('hold_released_at');
            $table->index(['tenant_id', 'match_status']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'match_status']);
            $table->dropConstrainedForeignId('hold_released_by');
            $table->dropColumn(['match_status', 'match_details', 'match_checked_at', 'hold_released_at', 'hold_release_reason']);
        });

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }
    }

    /** Existing installs don't re-run RbacSeeder. Owner/admin only by default. */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $name => $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['module' => 'purchase', 'entity' => 'bills', 'action' => $action, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
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
