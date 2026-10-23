<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerOpeningBalanceService;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Services\VendorOpeningBalanceService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * One-time (safe to re-run) repair for opening balances entered before
 * OpeningBalanceService:
 *  - openings saved on a customer/vendor but never posted (e.g. via the API);
 *  - openings posted on the day they were typed rather than the books' start;
 *  - openings offset to Suspense / Reserves instead of Opening Balance Difference.
 * Each is re-posted correctly; ones that are already correct are left alone.
 */
class PostOpeningBalances extends Command
{
    protected $signature = 'accounting:post-opening-balances
                            {--tenant= : Only this tenant id}
                            {--dry-run : Report what would be posted without changing anything}';

    protected $description = 'Post or re-post opening balances of ledgers, customers and vendors on the books\' start date';

    public function handle(
        ChartOfAccountsService $ledgers,
        CustomerOpeningBalanceService $customers,
        VendorOpeningBalanceService $vendors,
    ): int {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        $failures = 0;

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);
            $this->line("Tenant {$tenant->id} ({$tenant->name})");

            $work = [
                'ledger' => [$this->withOpening(ChartOfAccount::class, 'chart_of_account_opening_balance'), fn ($m) => $ledgers->syncOpeningBalance($m), fn ($m) => "{$m->code} {$m->name}"],
                'customer' => [$this->withOpening(Customer::class, 'customer_opening_balance'), fn ($m) => $customers->syncCustomerOpeningBalance($m), fn ($m) => $m->name],
                'vendor' => [$this->withOpening(Vendor::class, 'vendor_opening_balance'), fn ($m) => $vendors->syncVendorOpeningBalance($m), fn ($m) => $m->name],
            ];

            foreach ($work as $kind => [$models, $sync, $label]) {
                foreach ($models as $model) {
                    if ($this->option('dry-run')) {
                        $this->line("  would sync {$kind} {$label($model)}: " . number_format((float) $model->opening_balance, 2) . ' ' . ($model->opening_balance_type ?? ''));
                        continue;
                    }

                    try {
                        $journal = $sync($model);
                        $this->line("  {$kind} {$label($model)}: " . ($journal ? "posted as {$journal->journal_number}" : 'no opening'));
                    } catch (InvalidArgumentException $e) {
                        $failures++;
                        $this->error("  {$kind} {$label($model)}: {$e->getMessage()}");
                    }
                }
            }
        }

        if ($failures > 0) {
            $this->warn("{$failures} opening balance(s) could not be posted; fix the cause shown and run the command again.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Masters with an opening balance, plus any whose opening was posted before
     * and has since been cleared (so the old entry is reversed).
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    private function withOpening(string $modelClass, string $referenceType)
    {
        $postedIds = Journal::query()
            ->where('reference_type', $referenceType)
            ->where('status', Journal::STATUS_POSTED)
            ->pluck('reference_id');

        return $modelClass::query()
            ->where(fn ($q) => $q->where('opening_balance', '!=', 0)->orWhereIn('id', $postedIds))
            ->orderBy('id')
            ->get();
    }
}
