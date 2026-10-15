<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'delivery_challans',
        'delivery_challan_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'company_id')) {
                    $blueprint->foreignId('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'branch_id')) {
                    $blueprint->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
                }
            });
        }

        // 1. Backfill delivery_challans from parent production_orders where valid
        if (Schema::hasTable('delivery_challans') && Schema::hasTable('production_orders')) {
            $challans = DB::table('delivery_challans')
                ->whereNull('company_id')
                ->whereNotNull('production_order_id')
                ->get();

            foreach ($challans as $challan) {
                $order = DB::table('production_orders')->where('id', $challan->production_order_id)->first();
                if ($order && $order->company_id) {
                    DB::table('delivery_challans')->where('id', $challan->id)->update([
                        'company_id' => $order->company_id,
                        'branch_id' => $order->branch_id,
                    ]);
                }
            }
        }

        // 2. Backfill delivery_challans from source warehouse if still unassigned
        if (Schema::hasTable('delivery_challans') && Schema::hasTable('warehouses')) {
            $challans = DB::table('delivery_challans')
                ->whereNull('company_id')
                ->whereNotNull('warehouse_id')
                ->get();

            foreach ($challans as $challan) {
                $wh = DB::table('warehouses')->where('id', $challan->warehouse_id)->first();
                if ($wh && $wh->company_id) {
                    DB::table('delivery_challans')->where('id', $challan->id)->update([
                        'company_id' => $wh->company_id,
                        'branch_id' => $wh->branch_id,
                    ]);
                }
            }
        }

        // 3. Fallback: for any remaining orphaned records, derive from tenant's default company/branch
        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            $companyId = DB::table('companies')->where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?? DB::table('companies')->where('tenant_id', $tenantId)->value('id');

            if (! $companyId) {
                continue;
            }

            $branchId = DB::table('branches')->where('company_id', $companyId)->where('is_default', true)->value('id')
                ?? DB::table('branches')->where('tenant_id', $tenantId)->value('id');

            DB::table('delivery_challans')
                ->where('tenant_id', $tenantId)
                ->whereNull('company_id')
                ->update([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                ]);
        }

        // 4. Backfill delivery_challan_items from parent delivery_challan
        if (Schema::hasTable('delivery_challan_items') && Schema::hasTable('delivery_challans')) {
            $items = DB::table('delivery_challan_items')->whereNull('company_id')->get();
            foreach ($items as $item) {
                $parent = DB::table('delivery_challans')->where('id', $item->delivery_challan_id)->first();
                if ($parent && $parent->company_id) {
                    DB::table('delivery_challan_items')->where('id', $item->id)->update([
                        'company_id' => $parent->company_id,
                        'branch_id' => $parent->branch_id,
                    ]);
                }
            }
        }

        // 5. Add compound index for performance and isolation queries
        if (Schema::hasTable('delivery_challans')) {
            Schema::table('delivery_challans', function (Blueprint $table) {
                $table->index(['tenant_id', 'company_id', 'branch_id'], 'del_challans_tenant_co_branch_idx');
            });
        }
        if (Schema::hasTable('delivery_challan_items')) {
            Schema::table('delivery_challan_items', function (Blueprint $table) {
                $table->index(['tenant_id', 'company_id', 'branch_id'], 'del_challan_items_tenant_co_branch_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_challan_items')) {
            Schema::table('delivery_challan_items', function (Blueprint $table) {
                try {
                    $table->dropIndex('del_challan_items_tenant_co_branch_idx');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('delivery_challan_items', 'branch_id')) {
                    $table->dropConstrainedForeignId('branch_id');
                }
                if (Schema::hasColumn('delivery_challan_items', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
            });
        }

        if (Schema::hasTable('delivery_challans')) {
            Schema::table('delivery_challans', function (Blueprint $table) {
                try {
                    $table->dropIndex('del_challans_tenant_co_branch_idx');
                } catch (\Throwable) {
                }
                if (Schema::hasColumn('delivery_challans', 'branch_id')) {
                    $table->dropConstrainedForeignId('branch_id');
                }
                if (Schema::hasColumn('delivery_challans', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
            });
        }
    }
};
