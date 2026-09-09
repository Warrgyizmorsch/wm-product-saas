<?php

namespace App\Domains\Accounting\Concerns;

use App\Domains\Accounting\Services\AccountingAuditLogService;

/**
 * Applied to Accounting models whose plain create/update/delete lifecycle
 * should feed the Audit Trail automatically, so most of the module doesn't
 * need explicit logging calls. Actions that aren't a generic Eloquent event
 * (e.g. Journal posting/reversal) are logged explicitly by their service
 * instead — see JournalService::post()/reverse().
 */
trait LogsAccountingActivity
{
    public static function bootLogsAccountingActivity(): void
    {
        static::created(fn ($model) => $model->logAccountingActivity('created'));
        static::updated(fn ($model) => $model->logAccountingActivity('updated'));
        static::deleted(fn ($model) => $model->logAccountingActivity('deleted'));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logAccountingActivity(string $action, array $metadata = [], ?string $description = null): void
    {
        $label = class_basename($this);

        if ($action === 'updated' && empty($metadata)) {
            $metadata = ['changes' => $this->getChanges()];
        }

        app(AccountingAuditLogService::class)->record(
            $this,
            strtolower($label) . '.' . $action,
            "{$label} {$action}",
            $metadata,
            $description
        );
    }
}
