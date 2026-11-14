<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Listeners\PostCustomerPaymentJournal;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use App\Domains\Sales\Models\CustomerPayment;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * One-time (safe to re-run) repair for customer receipts saved as "Keep
 * Unallocated" before PostCustomerPaymentJournal posted them: they show as
 * Posted but never reached the ledger. Each gets its Dr Bank / Cr Accounts
 * Receivable journal on its payment date; receipts that already have one are
 * left alone.
 */
class PostUnallocatedReceipts extends Command
{
    protected $signature = 'accounting:post-unallocated-receipts
                            {--tenant= : Only this tenant id}
                            {--dry-run : Report what would be posted without changing anything}';

    protected $description = 'Post customer receipts that were kept unallocated and never reached the ledger';

    public function handle(PostCustomerPaymentJournal $listener): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        $missed = 0;

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);
            $this->line("Tenant {$tenant->id} ({$tenant->name})");

            $postedIds = Journal::query()
                ->where('reference_type', 'customer_payment')
                ->where('status', Journal::STATUS_POSTED)
                ->pluck('reference_id');

            $receipts = CustomerPayment::query()
                ->whereIn('status', ['Posted', 'posted', 'completed', 'paid'])
                ->whereDoesntHave('allocations')
                ->whereNotIn('id', $postedIds)
                ->orderBy('id')
                ->get();

            foreach ($receipts as $payment) {
                $label = "{$payment->payment_number} (" . number_format((float) $payment->amount, 2) . ", {$payment->payment_date})";

                if ($this->option('dry-run')) {
                    $this->line("  would post {$label}");
                    continue;
                }

                $listener->handle(new CustomerPaymentReceived($payment));
                $journal = Journal::query()->where('reference_type', 'customer_payment')
                    ->where('reference_id', $payment->id)->where('status', Journal::STATUS_POSTED)->first();

                if ($journal === null) {
                    $missed++;
                    $this->error("  {$label}: not posted — see Accounting posting failures for the reason");
                    continue;
                }

                $this->line("  {$label}: posted as {$journal->journal_number}");
            }
        }

        if ($missed > 0) {
            $this->warn("{$missed} receipt(s) could not be posted; fix the cause shown and run the command again.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
