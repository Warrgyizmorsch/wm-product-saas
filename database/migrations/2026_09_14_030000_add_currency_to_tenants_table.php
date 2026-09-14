<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One currency per tenant.
 *
 * Moves the base currency from companies.currency (and the loose
 * tenants.settings['currency'] key) to a real tenants.currency column. Every
 * company's currency is then aligned to its tenant's, so any code still reading
 * companies.currency stays correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'currency')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->char('currency', 3)->default('INR')->after('locale');
            });
        }

        foreach (DB::table('tenants')->get(['id', 'settings']) as $tenant) {
            $settings = json_decode((string) $tenant->settings, true) ?: [];
            $code = strtoupper(trim((string) ($settings['currency'] ?? '')));

            if ($code === '' || ! DB::table('currencies')->where('code', $code)->exists()) {
                $code = 'INR';
            }

            DB::table('tenants')->where('id', $tenant->id)->update(['currency' => $code]);
            DB::table('companies')->where('tenant_id', $tenant->id)->update(['currency' => $code]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'currency')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
