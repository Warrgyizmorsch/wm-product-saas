<?php

namespace App\Domains\HRMS\Commands;

use App\Domains\HRMS\Models\Document;
use App\Domains\HRMS\Models\Employee;
use App\Domains\Platform\Services\NotificationRuleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CheckDocumentExpiryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hrms:check-document-expiry';

    /**
     * The console command description.
     */
    protected $description = 'Scan employee statutory documents and trigger expiring-soon or expired alerts via Notification Master';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $this->info("Scanning statutory documents for expiration alerts on {$today->toDateString()}...");

        $documents = Document::query()
            ->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->where('documentable_type', Employee::class)
            ->with(['documentable'])
            ->get();

        $expiringCount = 0;
        $expiredCount = 0;

        foreach ($documents as $doc) {
            /** @var Employee|null $employee */
            $employee = $doc->documentable;
            if (!$employee) {
                continue;
            }

            $expiry = Carbon::parse($doc->expiry_date)->startOfDay();
            $diffDays = (int) $today->diffInDays($expiry, false);

            $alertHistory = is_array($doc->alert_history)
                ? $doc->alert_history
                : (json_decode($doc->alert_history ?? '[]', true) ?: []);

            if (empty($alertHistory) && !empty($doc->signature_metadata['alert_history'])) {
                $alertHistory = $doc->signature_metadata['alert_history'];
            }

            $expiryDateStr = $expiry->toDateString();
            $lastTrackedExpiry = $alertHistory['tracked_expiry_date'] ?? null;

            // If expiry_date has changed (document renewed), reset alert history
            if ($lastTrackedExpiry && $lastTrackedExpiry !== $expiryDateStr) {
                $alertHistory = [
                    'tracked_expiry_date' => $expiryDateStr,
                    'expiring_thresholds' => [],
                    'expired'             => false,
                ];
            } else {
                $alertHistory['tracked_expiry_date'] = $expiryDateStr;
            }

            $expiringThresholds = $alertHistory['expiring_thresholds'] ?? [];
            $expiredAlreadySent = !empty($alertHistory['expired']);

            try {
                // If document is expired ($diffDays <= 0) and expired alert hasn't been sent yet
                if ($diffDays <= 0) {
                    if (!$expiredAlreadySent) {
                        NotificationRuleService::trigger('hrms.document.expired', [
                            'tenant_id'     => $doc->tenant_id,
                            'employee_name' => $employee->full_name,
                            'employee_id'   => $employee->id,
                            'user_id'       => $employee->user_id,
                            'document_type' => $doc->name ?? 'Statutory Document',
                            'expired_on'    => $expiry->format('d M Y'),
                        ], $doc);

                        $alertHistory['expired'] = [
                            'sent_at'   => now()->toIso8601String(),
                            'date'      => $today->toDateString(),
                            'diff_days' => $diffDays,
                        ];

                        $this->saveAlertHistory($doc, $alertHistory);
                        $expiredCount++;
                    }
                }
                // If document expires in 30, 15, 7, 3, or 1 days and threshold alert hasn't been sent yet
                elseif (in_array($diffDays, [30, 15, 7, 3, 1], true)) {
                    if (!in_array($diffDays, $expiringThresholds, true)) {
                        NotificationRuleService::trigger('hrms.document.expiring_soon', [
                            'tenant_id'     => $doc->tenant_id,
                            'employee_name' => $employee->full_name,
                            'employee_id'   => $employee->id,
                            'user_id'       => $employee->user_id,
                            'document_type' => $doc->name ?? 'Statutory Document',
                            'expiry_date'   => $expiry->format('d M Y'),
                            'days_left'     => (string) $diffDays,
                        ], $doc);

                        $expiringThresholds[] = $diffDays;
                        $alertHistory['expiring_thresholds'] = $expiringThresholds;

                        $this->saveAlertHistory($doc, $alertHistory);
                        $expiringCount++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Document expiry alert failed for Document #{$doc->id}: " . $e->getMessage());
            }
        }

        $this->info("Completed: {$expiringCount} expiring alert(s) and {$expiredCount} expired alert(s) dispatched.");
        return 0;
    }

    /**
     * Persist alert history and last alert timestamp safely.
     */
    protected function saveAlertHistory(Document $doc, array $alertHistory): void
    {
        $now = now();
        $updateData = [
            'alert_history'        => $alertHistory,
            'last_expiry_alert_at' => $now,
        ];

        try {
            if (Schema::hasColumn('documents', 'alert_history')) {
                $doc->update($updateData);
                return;
            }
        } catch (\Throwable $e) {}

        // Fallback to signature_metadata if alert_history column is not yet migrated
        $metadata = is_array($doc->signature_metadata) ? $doc->signature_metadata : [];
        $metadata['alert_history'] = $alertHistory;
        $metadata['last_expiry_alert_at'] = $now->toIso8601String();
        $doc->update(['signature_metadata' => $metadata]);
    }
}
