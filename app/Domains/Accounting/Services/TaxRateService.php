<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\TaxRate;
use App\Domains\Accounting\Repositories\TaxRateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaxRateService
{
    public function __construct(
        private readonly TaxRateRepositoryInterface $taxRates,
    ) {
    }

    public function list(): Collection
    {
        return $this->taxRates->getAll();
    }

    public function active(): Collection
    {
        return $this->taxRates->getActive();
    }

    public function create(array $data): TaxRate
    {
        return $this->taxRates->create($data);
    }

    public function update(int $id, array $data): TaxRate
    {
        return $this->taxRates->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->taxRates->delete($id);
    }

    public function calculate(int $taxRateId, float $baseAmount): float
    {
        $taxRate = $this->taxRates->find($taxRateId);

        return $taxRate === null ? 0.0 : $taxRate->amountFor($baseAmount);
    }

    /**
     * The standard set of GST reference rates every tenant starts with. Called
     * both at tenant provisioning (ProvisionAccountingMasters listener) and by
     * AccountingChartOfAccountsSeeder for local/demo data. Idempotent
     * (updateOrCreate keyed on tenant_id + name), safe to re-run.
     *
     * NOTE: GST is not a single flat rate/account — it is a *tax group* made of
     * components (CGST + SGST for intra-state, or IGST for inter-state), each
     * posting to its own ledger. The current TaxRate shape (one `rate` +
     * one `tax_payable_account_id`) cannot represent this correctly — it will
     * either double-post or post the wrong split. Provisioning wires up the
     * underlying ledger accounts (via ChartOfAccountsService) but the TaxRate
     * model itself needs a `tax_components` (or similar) relation before GST
     * can be calculated correctly end to end. Flagging this as a model-level
     * change, not something provisioning can paper over.
     */
    public function provisionDefaultsIfMissing(int $tenantId, ?int $companyId = null, ?int $branchId = null): bool
    {
        if (TaxRate::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            // Rows seeded before a company existed are invisible in the UI (company-scoped);
            // attach them to the tenant's default organization instead of duplicating them.
            if ($companyId !== null) {
                TaxRate::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId, 'branch_id' => $branchId]);
            }

            return false;
        }

        $accounts = ChartOfAccount::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', ['2110', '2120', '2130', '1610', '1620', '1630'])
            ->pluck('id', 'code');

        // Seeded as reference/output-side rates for now. Once TaxRate supports
        // components, each of these should decompose into two 9% legs (CGST+SGST)
        // for intra-state and one 18% IGST leg for inter-state, rather than a
        // single flat rate/account pair.
        foreach ([0, 5, 12, 18, 28] as $rate) {
            TaxRate::query()->withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => "GST {$rate}%"],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'type' => 'gst',
                    'rate' => (float) $rate,
                    'is_compound' => false,
                    'is_active' => true,
                    // Placeholder: points at Output IGST until component support exists.
                    'tax_payable_account_id' => $accounts['2130'] ?? null,
                ]
            );
        }

        return true;
    }
}
