<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductionShift extends BaseModel
{
    use HasFactory;

    protected $table = 'production_shifts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'break_minutes',
        'overtime_allowed',
        'active',
    ];

    protected $casts = [
        'break_minutes'    => 'integer',
        'overtime_allowed' => 'boolean',
        'active'           => 'boolean',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    public function workCenters(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkCenter::class,
            'production_work_center_shifts',
            'shift_id',
            'work_center_id'
        );
    }

    /**
     * Determine if this shift crosses midnight into the next day (e.g. 10 PM to 6 AM).
     */
    public function isOvernight(): bool
    {
        if (empty($this->start_time) || empty($this->end_time)) {
            return false;
        }
        return $this->end_time < $this->start_time;
    }

    /**
     * Calculate net shift work hours accounting for midnight cross and break minutes.
     */
    public function durationHours(): float
    {
        if (empty($this->start_time) || empty($this->end_time)) {
            return 8.0;
        }

        $start = \Carbon\Carbon::parse('2000-01-01 ' . $this->start_time);
        $end = \Carbon\Carbon::parse('2000-01-01 ' . $this->end_time);

        if ($this->isOvernight()) {
            $end->addDay();
        }

        $grossMinutes = $start->diffInMinutes($end);
        $netMinutes = max(0, $grossMinutes - ($this->break_minutes ?? 0));

        return round($netMinutes / 60, 2);
    }

    /**
     * Format shift timing with overnight badge indicator if applicable.
     */
    public function getFormattedTimingAttribute(): string
    {
        if (empty($this->start_time) || empty($this->end_time)) {
            return 'N/A';
        }

        $startStr = \Carbon\Carbon::parse($this->start_time)->format('h:i A');
        $endStr = \Carbon\Carbon::parse($this->end_time)->format('h:i A');

        return $this->isOvernight()
            ? "{$startStr} - {$endStr} (+1 day)"
            : "{$startStr} - {$endStr}";
    }
}
