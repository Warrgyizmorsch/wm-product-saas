<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds Foreign Exchange Gain (4930) and Foreign Exchange Loss (5740) to tenants
 * provisioned before ChartOfAccountsService::provisionDefaults() seeded them.
 *
 * Deliberately inserts only these two rows rather than re-running
 * provisionDefaults() for every tenant: that uses updateOrCreate and would reset
 * names and is_active on system accounts a tenant has customised.
 */
return new class extends Migration
{
    /** @var array<int, array{code: string, name: string, type: string, subtype: string, normal_balance: string, parent: string}> */
    private array $accounts = [
        ['code' => '4930', 'name' => 'Foreign Exchange Gain', 'type' => 'income', 'subtype' => 'indirect_income', 'normal_balance' => 'credit', 'parent' => '4000'],
        ['code' => '5740', 'name' => 'Foreign Exchange Loss', 'type' => 'expense', 'subtype' => 'indirect_expense', 'normal_balance' => 'debit', 'parent' => '5000'],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->accounts as $account) {
            // Only tenants that already have a chart of accounts — identified by the
            // parent header. Tenants without one get the full set on provisioning.
            $parents = DB::table('chart_of_accounts')
                ->where('code', $account['parent'])
                ->whereNull('deleted_at')
                ->get(['id', 'tenant_id', 'company_id', 'branch_id']);

            foreach ($parents as $parent) {
                // No deleted_at filter: the (tenant_id, code) unique index covers
                // soft-deleted rows too, and a tenant that deleted it keeps it deleted.
                $exists = DB::table('chart_of_accounts')
                    ->where('tenant_id', $parent->tenant_id)
                    ->where('code', $account['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('chart_of_accounts')->insert([
                    'tenant_id' => $parent->tenant_id,
                    'company_id' => $parent->company_id,
                    'branch_id' => $parent->branch_id,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'subtype' => $account['subtype'],
                    'normal_balance' => $account['normal_balance'],
                    'parent_id' => $parent->id,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Leave any account that has already been posted to — deleting it would
        // orphan ledger history.
        DB::table('chart_of_accounts')
            ->whereIn('code', array_column($this->accounts, 'code'))
            ->where('is_system', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.chart_of_account_id', 'chart_of_accounts.id');
            })
            ->delete();
    }
};
