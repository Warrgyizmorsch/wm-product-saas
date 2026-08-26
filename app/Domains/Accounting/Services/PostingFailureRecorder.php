<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingPostingFailure;
use RuntimeException;

/**
 * Every auto-posting listener (PostSalesInvoiceJournal, PostPurchaseBillJournal,
 * etc.) swallows its own exceptions so a closed accounting period — or any other
 * posting error — never blocks the underlying Sales/Purchase/HRMS transaction.
 * Without this, that failure was only ever visible in the application log, so
 * a tenant's books could silently drift out of sync with its ERP transactions.
 * This makes the failure a queryable, resolvable record instead.
 */
class PostingFailureRecorder
{
    public function record(?int $tenantId, string $eventClass, object $model, string $message): void
    {
        AccountingPostingFailure::create([
            'tenant_id' => $tenantId,
            'event_class' => $eventClass,
            'model_class' => get_class($model),
            'model_id' => $model->id,
            'message' => $message,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Re-fire the original event for one failed posting. Listeners are not
     * queued, so this runs synchronously and — because every listener
     * already guards against double-posting — either succeeds silently or
     * records a fresh failure, which we detect by checking whether a new
     * failure row appeared for this same source record during the retry.
     */
    public function retry(AccountingPostingFailure $failure): bool
    {
        $model = $failure->model();

        if ($model === null) {
            throw new RuntimeException('The original record no longer exists.');
        }

        $eventClass = $failure->event_class;
        $attemptedAt = now();

        event(new $eventClass($model));

        $stillFailing = AccountingPostingFailure::query()
            ->where('model_class', $failure->model_class)
            ->where('model_id', $failure->model_id)
            ->where('occurred_at', '>=', $attemptedAt)
            ->exists();

        if ($stillFailing) {
            return false;
        }

        $failure->update([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return true;
    }
}
