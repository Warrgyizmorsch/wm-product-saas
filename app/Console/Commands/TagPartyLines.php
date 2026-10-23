<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (safe to re-run) repair: Receivable / Payable lines posted before
 * invoices and bills carried their party have no customer / vendor tag, so the
 * party ledger shows the payment but not the invoice, and the Balance Sheet
 * reads that customer as being in credit. Fills the tag from the source
 * document; amounts are not touched.
 */
class TagPartyLines extends Command
{
    protected $signature = 'accounting:tag-party-lines
                            {--tenant= : Only this tenant id}
                            {--dry-run : Report what would be tagged without changing anything}';

    protected $description = 'Tag untagged Receivable/Payable journal lines with the customer or vendor of their source document';

    /** reference_type => [control account key, party type, table, party column] */
    private const SOURCES = [
        'Invoice' => [SystemAccount::AR, 'customer', 'invoices', 'customer_id'],
        'invoice' => [SystemAccount::AR, 'customer', 'invoices', 'customer_id'],
        'customer_payment' => [SystemAccount::AR, 'customer', 'customer_payments', 'customer_id'],
        'vendor_bill' => [SystemAccount::AP, 'vendor', 'vendor_bills', 'vendor_id'],
        'vendor_payment' => [SystemAccount::AP, 'vendor', 'vendor_payments', 'vendor_id'],
    ];

    public function handle(SystemAccountService $systemAccounts): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);
            $this->line("Tenant {$tenant->id} ({$tenant->name})");

            foreach (self::SOURCES as $referenceType => [$controlKey, $partyType, $table, $column]) {
                $control = $systemAccounts->get($controlKey, $tenant->id);
                if ($control === null) {
                    continue;
                }

                $lines = JournalEntry::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('chart_of_account_id', $control->id)
                    ->whereNull('party_id')
                    ->whereHas('journal', fn ($q) => $q->withoutGlobalScopes()->where('reference_type', $referenceType))
                    ->with(['journal' => fn ($q) => $q->withoutGlobalScopes()])
                    ->get();

                foreach ($lines as $line) {
                    $document = DB::table($table)->where('id', $line->journal->reference_id)->first();
                    $partyId = $document->{$column} ?? null;

                    // Older invoices kept the customer on their sales order only.
                    if ($partyId === null && $table === 'invoices' && ! empty($document?->sales_order_id)) {
                        $partyId = DB::table('sales_orders')->where('id', $document->sales_order_id)->value('customer_id');
                    }

                    $label = "{$line->journal->journal_number} ({$referenceType} #{$line->journal->reference_id})";

                    if ($partyId === null) {
                        $this->warn("  {$label}: source document has no {$partyType}; left as is");
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("  would tag {$label} -> {$partyType} #{$partyId}");
                        continue;
                    }

                    DB::table('journal_entries')->where('id', $line->id)->update(['party_type' => $partyType, 'party_id' => $partyId]);
                    $this->line("  tagged {$label} -> {$partyType} #{$partyId}");
                }
            }
        }

        return self::SUCCESS;
    }
}
