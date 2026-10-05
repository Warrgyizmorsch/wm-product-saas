<?php

use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every provisioned account a stable system_key, so posting logic can
 * find "Accounts Receivable" without depending on its code — which leaves the
 * code free for the tenant to renumber.
 *
 * Backfill matches system accounts by their original template code. A tenant
 * that already renumbered a system account (possible before the code lock)
 * keeps a null key on it; SystemAccountService falls back to the template code
 * for those, which is exactly how posting resolved it before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('system_key', 64)->nullable()->after('code');
            $table->unique(['tenant_id', 'system_key']);
        });

        // One UPDATE per template row covers every tenant. Soft-deleted rows are
        // included on purpose: the unique index covers them too.
        foreach (SystemAccount::TEMPLATE as $key => $code) {
            DB::table('chart_of_accounts')
                ->where('code', $code)
                ->where('is_system', true)
                ->whereNull('system_key')
                ->update(['system_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }
};
