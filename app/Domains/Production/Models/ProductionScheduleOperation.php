<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScheduleOperation extends BaseModel
{
    protected $table = 'production_schedule_operations';

    // ─── MES Operation Status Constants ──────────────────────────────────────
    // NOTE: These are execution statuses only — never set on ProductionSchedule.

    public const STATUS_WAITING   = 'waiting';
    public const STATUS_READY     = 'ready';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED   = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_READY,
        self::STATUS_RUNNING,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_SKIPPED,
        self::STATUS_CANCELLED,
    ];

    // ─── Fillable ─────────────────────────────────────────────────────────────

    protected $fillable = [
        'tenant_id',
        'production_schedule_id',
        'production_order_id',
        'production_order_operation_id',
        'work_center_id',
        'machine_id',
        'sequence',
        'priority',
        'planned_start',
        'planned_finish',
        'planned_duration_minutes',
        'actual_start',
        'actual_finish',
        'status',
        'last_paused_at',
        'accumulated_paused_seconds',
        'shift_code',
        'quality_checkpoint_status',
        'maintenance_hold_status',
        'lane',
        'resource_id',
        'warnings',
        'locked',
        'actual_machine_id',
    ];

    protected $casts = [
        'planned_start'            => 'datetime',
        'planned_finish'           => 'datetime',
        'actual_start'             => 'datetime',
        'actual_finish'            => 'datetime',
        'last_paused_at'           => 'datetime',
        'accumulated_paused_seconds' => 'integer',
        'planned_duration_minutes' => 'float',
        'sequence'                 => 'integer',
        'priority'                 => 'integer',
        'warnings'                 => 'array',
        'locked'                   => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function orderOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function actualMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'actual_machine_id');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Whether this operation can be started by an operator.
     */
    public function canStart(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    /**
     * Whether this operation has effectively ended (terminal state).
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_SKIPPED,
            self::STATUS_CANCELLED,
        ]);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeRunning(Builder $query): void
    {
        $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeReady(Builder $query): void
    {
        $query->where('status', self::STATUS_READY);
    }

    public function scopeForMachine(Builder $query, int $machineId): void
    {
        $query->where('machine_id', $machineId);
    }

    protected static function booted()
    {
        static::saving(function ($operation) {
            if ($operation->warnings && is_array($operation->warnings)) {
                $operation->warnings = self::aggregateWarnings($operation->warnings);
            }
        });
    }

    public static function aggregateWarnings(array $warnings): array
    {
        $holidaySkippedDates = [];
        $otherWarnings = [];

        foreach ($warnings as $w) {
            if (isset($w['code']) && $w['code'] === 'HOLIDAY_SKIPPED') {
                preg_match_all('/\d{4}-\d{2}-\d{2}/', $w['message'] ?? '', $matches);
                if (!empty($matches[0])) {
                    $holidaySkippedDates = array_merge($holidaySkippedDates, $matches[0]);
                }
            } else {
                $otherWarnings[] = $w;
            }
        }

        if (!empty($holidaySkippedDates)) {
            $holidaySkippedDates = array_values(array_unique($holidaySkippedDates));
            sort($holidaySkippedDates);
            $count = count($holidaySkippedDates);
            if ($count === 1) {
                $msg = "Scheduled date {$holidaySkippedDates[0]} skipped due to holiday/weekend configuration.";
            } else {
                $msg = "{$count} day(s) skipped due to holiday/weekend configuration (" . implode(', ', $holidaySkippedDates) . ").";
            }
            $otherWarnings[] = [
                'code'     => 'HOLIDAY_SKIPPED',
                'message'  => $msg,
                'severity' => 'info',
            ];
        }

        // Deduplicate other warnings by code and message
        $uniqueWarnings = [];
        $seen = [];
        foreach ($otherWarnings as $w) {
            $key = ($w['code'] ?? '') . '_' . ($w['message'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueWarnings[] = $w;
            }
        }

        return $uniqueWarnings;
    }
}
