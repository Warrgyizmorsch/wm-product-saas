<?php

namespace App\Console\Commands;

use App\Domains\Platform\Services\NotificationRuleService;
use App\Domains\Purchase\Models\VendorDocument;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * FRS 2.3 / C-19: daily scan of vendor compliance documents (MSME, Form 13, ISO, agreements...).
 * Sends an "expiring soon" alert once per reminder day and an "expired" alert once, through
 * Notification Master, and keeps the stored status in step with the expiry date.
 */
class CheckVendorDocumentExpiryCommand extends Command
{
    protected $signature = 'purchase:check-vendor-document-expiry {--tenant= : Filter by Tenant ID}';

    protected $description = 'Alert on vendor compliance documents that are expiring soon or have expired';

    public function handle(): int
    {
        $today = Carbon::today();
        $expiring = 0;
        $expired = 0;

        $query = VendorDocument::withoutGlobalScopes()
            ->whereNotNull('expiry_date')
            ->whereHas('vendor', fn ($q) => $q->withoutGlobalScopes()->whereNull('merged_into_id'))
            ->with(['vendor' => fn ($q) => $q->withoutGlobalScopes()]);

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $query->each(function (VendorDocument $doc) use ($today, &$expiring, &$expired): void {
            $expiry = $doc->expiry_date->copy()->startOfDay();
            $daysLeft = (int) $today->diffInDays($expiry, false);

            // A renewed document (new expiry date) starts a fresh alert history.
            $history = $doc->alert_history ?? [];
            if (($history['expiry_date'] ?? null) !== $expiry->toDateString()) {
                $history = ['expiry_date' => $expiry->toDateString(), 'sent_days' => [], 'expired' => false];
            }

            $payload = [
                'tenant_id' => $doc->tenant_id,
                'vendor_name' => $doc->vendor->name,
                'document_type' => VendorDocument::TYPES[$doc->document_type] ?? $doc->document_type,
                'document_name' => $doc->document_name,
                'expiry_date' => $expiry->format('d M Y'),
                'days_left' => (string) max(0, $daysLeft),
            ];

            try {
                if ($daysLeft <= 0) {
                    if (empty($history['expired'])) {
                        NotificationRuleService::trigger('purchase.vendor_document.expired', $payload, $doc->vendor);
                        $history['expired'] = true;
                        $expired++;
                    }
                } else {
                    // Every reminder day we have reached and not yet alerted about. A document uploaded
                    // 10 days before expiry has passed the 30 and 15 day marks: alert once, not twice.
                    $due = array_values(array_filter(
                        $doc->reminder_days ?: VendorDocument::DEFAULT_REMINDER_DAYS,
                        fn ($day) => $daysLeft <= (int) $day && ! in_array((int) $day, $history['sent_days'], true)
                    ));

                    if ($due !== []) {
                        NotificationRuleService::trigger('purchase.vendor_document.expiring_soon', $payload, $doc->vendor);
                        $history['sent_days'] = array_values(array_unique(array_merge($history['sent_days'], array_map('intval', $due))));
                        $expiring++;
                    }
                }

                $doc->update(['alert_history' => $history, 'status' => $doc->computed_status]);
            } catch (\Throwable $e) {
                Log::warning("Vendor document expiry alert failed for document #{$doc->id}: " . $e->getMessage());
            }
        });

        $this->info("Vendor documents: {$expiring} expiring alert(s), {$expired} expired alert(s) sent.");

        return self::SUCCESS;
    }
}
