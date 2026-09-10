<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('journal_entries', 'party_type')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->string('party_type')->nullable()->after('cost_center_id');
                $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
                $table->index(['party_type', 'party_id']);
            });
        }

        if (!Schema::hasColumn('customers', 'opening_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->default(0.00)->after('status');
            });
        }

        // Defensive: an earlier migration (2026_09_02_170000) is recorded as
        // run in some environments but its `vendor_id` column add did not
        // take effect there (observed on local dev) — the Party Ledger's
        // Transporter-reuses-Vendor-Ledger picker depends on this column
        // existing, so guard it here too rather than assuming the earlier
        // migration's migrations-table record reflects the real schema.
        if (Schema::hasTable('transporters') && !Schema::hasColumn('transporters', 'vendor_id')) {
            Schema::table('transporters', function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('name')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'opening_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('opening_balance');
            });
        }

        if (Schema::hasColumn('journal_entries', 'party_type')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex(['party_type', 'party_id']);
                $table->dropColumn(['party_type', 'party_id']);
            });
        }
    }
};
