<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\TaxRate;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();

        if (!$tenant) {
            return;
        }

        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);
        $this->seedTaxRates($tenant->id);
        $this->seedCurrentFiscalYear($tenant->id);
    }

    /**
     * NOTE: GST is not a single flat rate/account — it is a *tax group* made of
     * components (CGST + SGST for intra-state, or IGST for inter-state), each
     * posting to its own ledger. The current TaxRate shape (one `rate` +
     * one `tax_payable_account_id`) cannot represent this correctly — it will
     * either double-post or post the wrong split. This seeder wires up the
     * underlying ledger accounts (done above) but the TaxRate model itself
     * needs a `tax_components` (or similar) relation before GST can be
     * calculated correctly end to end. Flagging this as a model-level
     * change, not something a seeder can paper over.
     */
    private function seedTaxRates(int $tenantId): void
    {
        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', ['2110', '2120', '2130', '1610', '1620', '1630'])
            ->pluck('id', 'code');

        // Seeded as reference/output-side rates for now. Once TaxRate supports
        // components, each of these should decompose into two 9% legs (CGST+SGST)
        // for intra-state and one 18% IGST leg for inter-state, rather than a
        // single flat rate/account pair.
        foreach ([0, 5, 12, 18, 28] as $rate) {
            TaxRate::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => "GST {$rate}%"],
                [
                    'type' => 'gst',
                    'rate' => (float) $rate,
                    'is_compound' => false,
                    'is_active' => true,
                    // Placeholder: points at Output IGST until component support exists.
                    'tax_payable_account_id' => $accounts['2130'] ?? null,
                ]
            );
        }
    }

    private function seedCurrentFiscalYear(int $tenantId): void
    {
        // India: statutory fiscal year is 1 April – 31 March, NOT calendar year.
        // now()->startOfYear()/endOfYear() default to Jan–Dec and will silently
        // produce the wrong FY for every Indian tenant.
        $today = now();
        $fyStartYear = $today->month >= 4 ? $today->year : $today->year - 1;

        $startDate = now()->setDate($fyStartYear, 4, 1)->startOfDay();
        $endDate = (clone $startDate)->addYear()->subDay()->endOfDay();

        $existing = FiscalYear::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('start_date', $startDate->toDateString())
            ->exists();

        if ($existing) {
            return;
        }

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . $fyStartYear . '-' . ($fyStartYear + 1),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }
}
