<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScanLog extends BaseModel
{
    use HasFactory;

    protected $table = 'production_scan_logs';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /**
     * Supported entity_type values.
     * Legacy: order | batch | serial
     * Extended: product | machine | work_center | warehouse | operator
     */
    public const ENTITY_TYPES = [
        'order', 'batch', 'serial',
        'product', 'machine', 'work_center', 'warehouse', 'operator',
    ];

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'raw_code',         // The literal business-identifier string scanned
        'scan_type',
        'status',           // success | failed
        'action_taken',     // view | issue_material | receive_fg | log_scrap | etc.
        'failure_reason',
        'scanned_by',
        'device_identifier',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Resolve a human-readable entity code for display in the scan log list.
     * Falls back to the raw_code captured at scan time if entity lookup fails.
     */
    public function getEntityCode(): string
    {
        // Prefer the raw_code stored at scan time (avoids N+1 lookups)
        if (! empty($this->raw_code)) {
            return $this->raw_code;
        }

        return match ($this->entity_type) {
            'order'  => ProductionOrder::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->find($this->entity_id)?->order_number ?? '—',
            'batch'  => ProductionBatch::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->find($this->entity_id)?->batch_number ?? '—',
            'serial' => ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->find($this->entity_id)?->serial_number ?? '—',
            default  => '—',
        };
    }
}
