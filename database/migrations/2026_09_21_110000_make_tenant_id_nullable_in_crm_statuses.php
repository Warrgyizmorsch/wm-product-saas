<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Make tenant_id nullable in lead_statuses
        if (Schema::hasTable('lead_statuses')) {
            Schema::table('lead_statuses', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // 2. Make tenant_id nullable in deal_statuses
        if (Schema::hasTable('deal_statuses')) {
            Schema::table('deal_statuses', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        $now = now();

        // 3. Consolidate default Lead Statuses to global (tenant_id = NULL)
        $defaultLeadStatuses = [
            ['name' => 'New',       'sort_order' => 1, 'color' => 'bg-primary'],
            ['name' => 'Qualified', 'sort_order' => 2, 'color' => 'bg-teal'],
            ['name' => 'Dealing',   'sort_order' => 3, 'color' => 'bg-info'],
            ['name' => 'Won',       'sort_order' => 4, 'color' => 'bg-success'],
            ['name' => 'Lost',      'sort_order' => 5, 'color' => 'bg-danger'],
        ];

        foreach ($defaultLeadStatuses as $status) {
            $exists = DB::table('lead_statuses')
                ->whereNull('tenant_id')
                ->where('name', $status['name'])
                ->first();

            if (! $exists) {
                $existing = DB::table('lead_statuses')
                    ->where('name', $status['name'])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($existing) {
                    DB::table('lead_statuses')
                        ->where('id', $existing->id)
                        ->update([
                            'tenant_id' => null,
                            'company_id' => null,
                            'branch_id' => null,
                            'sort_order' => $status['sort_order'],
                            'color' => $status['color'],
                            'is_protected' => true,
                            'is_active' => true,
                            'updated_at' => $now,
                        ]);
                    // Delete duplicate default status rows for other tenants
                    DB::table('lead_statuses')
                        ->where('name', $status['name'])
                        ->where('id', '!=', $existing->id)
                        ->delete();
                } else {
                    DB::table('lead_statuses')->insert([
                        'tenant_id' => null,
                        'company_id' => null,
                        'branch_id' => null,
                        'name' => $status['name'],
                        'sort_order' => $status['sort_order'],
                        'color' => $status['color'],
                        'is_protected' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } else {
                DB::table('lead_statuses')
                    ->where('name', $status['name'])
                    ->whereNotNull('tenant_id')
                    ->delete();
            }
        }

        // 4. Consolidate default Deal Statuses to global (tenant_id = NULL)
        $defaultDealStatuses = [
            ['name' => 'Qualification',  'sort_order' => 1, 'color' => 'bg-primary', 'probability' => 10,  'is_protected' => false],
            ['name' => 'Needs Analysis', 'sort_order' => 2, 'color' => 'bg-info',    'probability' => 30,  'is_protected' => false],
            ['name' => 'Proposal',       'sort_order' => 3, 'color' => 'bg-warning', 'probability' => 60,  'is_protected' => false],
            ['name' => 'Negotiation',    'sort_order' => 4, 'color' => 'bg-dark',    'probability' => 80,  'is_protected' => false],
            ['name' => 'Won',            'sort_order' => 5, 'color' => 'bg-success', 'probability' => 100, 'is_protected' => true],
            ['name' => 'Lost',           'sort_order' => 6, 'color' => 'bg-danger',  'probability' => 0,   'is_protected' => true],
        ];

        foreach ($defaultDealStatuses as $status) {
            $exists = DB::table('deal_statuses')
                ->whereNull('tenant_id')
                ->where('name', $status['name'])
                ->first();

            if (! $exists) {
                $existing = DB::table('deal_statuses')
                    ->where('name', $status['name'])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($existing) {
                    DB::table('deal_statuses')
                        ->where('id', $existing->id)
                        ->update([
                            'tenant_id' => null,
                            'company_id' => null,
                            'branch_id' => null,
                            'sort_order' => $status['sort_order'],
                            'color' => $status['color'],
                            'probability' => $status['probability'],
                            'is_protected' => $status['is_protected'],
                            'is_active' => true,
                            'updated_at' => $now,
                        ]);
                    // Delete duplicates for other tenants
                    DB::table('deal_statuses')
                        ->where('name', $status['name'])
                        ->where('id', '!=', $existing->id)
                        ->delete();
                } else {
                    DB::table('deal_statuses')->insert([
                        'tenant_id' => null,
                        'company_id' => null,
                        'branch_id' => null,
                        'name' => $status['name'],
                        'sort_order' => $status['sort_order'],
                        'color' => $status['color'],
                        'probability' => $status['probability'],
                        'is_protected' => $status['is_protected'],
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } else {
                DB::table('deal_statuses')
                    ->where('name', $status['name'])
                    ->whereNotNull('tenant_id')
                    ->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_statuses')) {
            DB::table('lead_statuses')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            Schema::table('lead_statuses', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->default(1)->change();
            });
        }

        if (Schema::hasTable('deal_statuses')) {
            DB::table('deal_statuses')->whereNull('tenant_id')->update(['tenant_id' => 1]);
            Schema::table('deal_statuses', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->default(1)->change();
            });
        }
    }
};
