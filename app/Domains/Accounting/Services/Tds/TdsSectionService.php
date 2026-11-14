<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\TdsSection;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;

/**
 * The TDS section master and the payable ledger each section posts to
 * (Tally keeps one duty ledger per nature of payment; so do we). Salary TDS
 * (192) stays on the original TDS Payable 2140.
 */
class TdsSectionService
{
    /**
     * Starter sections with the rates and limits in force for FY 2025-26
     * onwards. Every tenant can edit them; they are only seeded once.
     */
    public const DEFAULTS = [
        ['code' => '194C', 'name' => 'Contractors', 'nature' => 'Payment to contractors / sub-contractors', 'ind' => 1, 'other' => 2, 'single' => 30000, 'aggregate' => 100000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2141'],
        ['code' => '194J', 'name' => 'Professional fees', 'nature' => 'Fees for professional services', 'ind' => 10, 'other' => 10, 'single' => 50000, 'aggregate' => 50000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2142'],
        ['code' => '194J', 'name' => 'Technical services', 'nature' => 'Fees for technical services / royalty', 'ind' => 2, 'other' => 2, 'single' => 50000, 'aggregate' => 50000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2143'],
        ['code' => '194I', 'name' => 'Rent - plant & machinery', 'nature' => 'Rent on plant, machinery or equipment', 'ind' => 2, 'other' => 2, 'single' => null, 'aggregate' => 50000, 'period' => TdsSection::PERIOD_MONTHLY, 'ledger' => '2144'],
        ['code' => '194I', 'name' => 'Rent - land & building', 'nature' => 'Rent on land, building or furniture', 'ind' => 10, 'other' => 10, 'single' => null, 'aggregate' => 50000, 'period' => TdsSection::PERIOD_MONTHLY, 'ledger' => '2145'],
        ['code' => '194H', 'name' => 'Commission / brokerage', 'nature' => 'Commission or brokerage', 'ind' => 2, 'other' => 2, 'single' => null, 'aggregate' => 20000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2146'],
        ['code' => '194Q', 'name' => 'Purchase of goods', 'nature' => 'Purchase of goods above Rs 50 lakh a year', 'ind' => 0.1, 'other' => 0.1, 'single' => null, 'aggregate' => 5000000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2147', 'excess' => true],
        ['code' => '194A', 'name' => 'Interest (other than securities)', 'nature' => 'Interest other than on securities', 'ind' => 10, 'other' => 10, 'single' => null, 'aggregate' => 10000, 'period' => TdsSection::PERIOD_ANNUAL, 'ledger' => '2148'],
    ];

    public function __construct(
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * Seed the section master (and its ledgers) for a tenant that has none.
     * Called at tenant provisioning and lazily from the TDS screens and the
     * calculator, so tenants created before TDS existed get it too.
     */
    public function provisionDefaultsIfMissing(int $tenantId): bool
    {
        if (TdsSection::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->nonSalary()->exists()) {
            $this->salarySection($tenantId);

            return false;
        }

        foreach (self::DEFAULTS as $row) {
            $ledger = $this->ledgerFor($tenantId, $row['ledger'], "TDS Payable - {$row['code']} {$row['name']}");

            TdsSection::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'code' => $row['code'],
                'name' => $row['name'],
                'nature_of_payment' => $row['nature'],
                'rate_individual' => $row['ind'],
                'rate_other' => $row['other'],
                'rate_no_pan' => 20,
                'single_threshold' => $row['single'],
                'aggregate_threshold' => $row['aggregate'],
                'threshold_period' => $row['period'],
                'deduct_on_excess_only' => $row['excess'] ?? false,
                'payable_account_id' => $ledger?->id,
                'is_active' => true,
            ]);
        }

        $this->salarySection($tenantId);

        return true;
    }

    /**
     * Section 192 (salary), on the original TDS Payable 2140 that payroll has
     * always credited. Created on demand, also for tenants seeded before it existed.
     */
    public function salarySection(int $tenantId): TdsSection
    {
        $existing = TdsSection::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', TdsSection::SALARY_CODE)->first();

        if ($existing !== null) {
            return $existing;
        }

        return TdsSection::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'code' => TdsSection::SALARY_CODE,
            'name' => 'Salary',
            'nature_of_payment' => 'Salary paid to employees (slab rates, deducted by payroll)',
            'rate_individual' => 0,
            'rate_other' => 0,
            'rate_no_pan' => 20,
            'single_threshold' => null,
            'aggregate_threshold' => null,
            'threshold_period' => TdsSection::PERIOD_PER_TRANSACTION,
            'payable_account_id' => $this->systemAccounts->get(SystemAccount::TDS_PAYABLE, $tenantId)?->id,
            'is_active' => true,
        ]);
    }

    /** The ledger a section posts to, created on first use if it was removed or never made. */
    public function payableAccount(TdsSection $section): ?ChartOfAccount
    {
        $account = $section->payable_account_id
            ? ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $section->tenant_id)->find($section->payable_account_id)
            : null;

        if ($account === null) {
            $account = $this->ledgerFor((int) $section->tenant_id, $this->freeCode((int) $section->tenant_id), "TDS Payable - {$section->code} {$section->name}");
            $section->forceFill(['payable_account_id' => $account?->id])->save();
        }

        return $account;
    }

    private function ledgerFor(int $tenantId, string $code, string $name): ?ChartOfAccount
    {
        $existing = ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $code)->first();

        if ($existing !== null) {
            // The code is taken by something else: never hijack a tenant's own ledger.
            return str_starts_with((string) $existing->name, 'TDS Payable') ? $existing : $this->ledgerFor($tenantId, $this->freeCode($tenantId), $name);
        }

        // Sits next to the original TDS Payable, in the same group.
        $parent = $this->systemAccounts->get(SystemAccount::TDS_PAYABLE, $tenantId);

        return ChartOfAccount::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'type' => ChartOfAccount::TYPE_LIABILITY,
            'subtype' => ChartOfAccount::SUBTYPE_DUTIES_TAXES,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'parent_id' => $parent?->parent_id,
            'ledger_group_id' => $parent?->ledger_group_id,
            'is_active' => true,
        ]);
    }

    private function freeCode(int $tenantId): string
    {
        $taken = ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('code', 'like', '21%')->pluck('code')->all();

        for ($code = 2141; $code <= 2199; $code++) {
            if (! in_array((string) $code, $taken, true)) {
                return (string) $code;
            }
        }

        return '2140-' . (count($taken) + 1);
    }
}
