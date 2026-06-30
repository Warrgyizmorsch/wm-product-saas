<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Loggable — Lightweight Audit Logging Trait
 *
 * A7: Records model lifecycle events (created, updated, deleted) to the application log.
 * Applied to: WorkCenter, Machine, Routing, RoutingOperation.
 *
 * Current implementation: writes to Laravel log (storage/logs).
 * Future implementation: replace Log::info() body with AuditLog::record()
 * when the dedicated Audit module is built — zero model changes required.
 */
trait Loggable
{
    protected static function bootLoggable(): void
    {
        static::created(function ($model): void {
            static::writeAuditLog('created', $model);
        });

        static::updated(function ($model): void {
            static::writeAuditLog('updated', $model, $model->getDirty());
        });

        static::deleted(function ($model): void {
            // This fires for both soft-delete and hard-delete
            $action = method_exists($model, 'trashed') && $model->trashed()
                ? 'soft_deleted'
                : 'force_deleted';
            static::writeAuditLog($action, $model);
        });
    }

    /**
     * Write a structured audit log entry.
     * Future: replace body with AuditLog::record($event, $model, $changed)
     */
    private static function writeAuditLog(string $event, $model, array $changed = []): void
    {
        Log::info('[AUDIT] ' . $event . ': ' . class_basename($model), [
            'event'     => $event,
            'model'     => class_basename($model),
            'model_id'  => $model->id,
            'tenant_id' => $model->tenant_id ?? null,
            'user_id'   => auth()->id(),
            'changed'   => $changed,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
