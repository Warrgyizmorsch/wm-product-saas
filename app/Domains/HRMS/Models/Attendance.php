<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Traits\HasHrmsScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends BaseModel
{
    use HasHrmsScope;

    protected $table = 'attendances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'location_type',
        'status',
        'total_work_hours',
        'total_break_hours',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'check_in_selfie_path',
        'check_out_selfie_path',
        'total_distance_km',
        'route_polyline',
        'route_snapshot_url',
        'route_summary',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_work_hours' => 'decimal:2',
        'total_break_hours' => 'decimal:2',
        'total_distance_km' => 'decimal:2',
        'route_summary' => 'array',
    ];

    /**
     * Get normalized route snapshot URL.
     */
    public function getRouteSnapshotUrlAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:image/')) {
            return $value;
        }
        if (str_starts_with($value, 'storage/') || str_starts_with($value, '/storage/')) {
            return asset(ltrim($value, '/'));
        }
        return asset('storage/' . ltrim($value, '/'));
    }

    /**
     * Get location logs seamlessly: uses live raw logs if present,
     * or decoded polyline points if raw logs have been purged after checkout.
     */
    public function getFormattedLocationLogsAttribute(): array
    {
        if ($this->relationLoaded('locationLogs') ? $this->locationLogs->isNotEmpty() : $this->locationLogs()->exists()) {
            return $this->locationLogs->map(fn($l) => [
                'lat'  => (float) $l->latitude,
                'lng'  => (float) $l->longitude,
                'time' => $l->created_at ? $l->created_at->format('h:i A') : '',
            ])->values()->toArray();
        }

        if (!empty($this->route_polyline)) {
            $summary = is_array($this->route_summary) ? $this->route_summary : (json_decode($this->route_summary, true) ?: []);
            $times = $summary['waypoint_times'] ?? [];
            return app(\App\Domains\HRMS\Services\AttendanceRouteService::class)->decodePolyline($this->route_polyline, $times);
        }

        return [];
    }

    /**
     * Location tracking settings from Attendance Rules:
     * If unchecked in rules, tracking is skipped.
     * If checked, interval_minutes comes from the rule.
     */
    public function getTrackingConfigAttribute(): array
    {
        $employee = $this->employee;
        $rule = AttendanceRule::forEmployee($employee);

        $locationType = $this->location_type;
        $isTracking = $rule ? $rule->isTrackingEnabled($locationType) : false;
        $intervalMinutes = $isTracking ? $rule->getTrackingMinutes($locationType) : null;

        return [
            'enabled'          => $isTracking,
            'interval_minutes' => $intervalMinutes,
            'interval_seconds' => $intervalMinutes ? ($intervalMinutes * 60) : null,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class, 'attendance_id');
    }

    public function isWorking(): bool
    {
        return !empty($this->check_in) && empty($this->check_out);
    }

    public function isOnBreak(): bool
    {
        return $this->breaks()->whereNull('break_out')->exists();
    }

    public function activeBreak()
    {
        return $this->breaks()->whereNull('break_out')->first();
    }

    public function formatHours(?float $hoursVal): string
    {
        if (!$hoursVal || $hoursVal <= 0) {
            return '-';
        }
        $totalMinutes = round($hoursVal * 60);
        if ($totalMinutes < 60) {
            return $totalMinutes . 'm';
        }
        $hrs = floor($totalMinutes / 60);
        $mins = $totalMinutes % 60;
        if ($mins === 0) {
            return $hrs . 'hr';
        }
        return $hrs . 'hr ' . $mins . 'm';
    }

    public function getFormattedWorkHoursAttribute(): string
    {
        return $this->formatHours($this->total_work_hours);
    }

    public function getFormattedBreakHoursAttribute(): string
    {
        return $this->formatHours($this->total_break_hours);
    }

    public function getFormattedLocationTypeAttribute(): string
    {
        $loc = $this->location_type;
        if (!$loc && $this->employee) {
            $loc = $this->employee->office;
        }
        if (!$loc) {
            return 'Office';
        }
        $lower = strtolower($loc);
        if ($lower === 'office') {
            return 'Office';
        }
        if ($lower === 'wfh') {
            return 'WFH';
        }
        if (in_array($lower, ['onsite', 'on-site', 'site'])) {
            return 'On-Site';
        }
        return ucfirst($loc);
    }

    public function locationLogs(): HasMany
    {
        return $this->hasMany(AttendanceLocationLog::class, 'attendance_id');
    }

    protected static function booted()
    {
        static::saved(function ($attendance) {
            if ($attendance->employee_id && $attendance->check_out && (float)$attendance->total_work_hours > 0) {
                try {
                    $employee = $attendance->employee;
                    if ($employee) {
                        $dateStr = $attendance->date instanceof \Carbon\Carbon 
                            ? $attendance->date->toDateString() 
                            : \Carbon\Carbon::parse($attendance->date)->toDateString();

                        $shift = $employee->resolveShiftForDate($dateStr);
                        $expectedHours = 8.0;
                        if ($shift) {
                            $startTime = \Carbon\Carbon::parse($dateStr . ' ' . $shift->start_time);
                            $endTime = \Carbon\Carbon::parse($dateStr . ' ' . $shift->end_time);
                            if ($endTime->lessThan($startTime)) {
                                $endTime->addDay();
                            }
                            $shiftDurationMinutes = $startTime->diffInMinutes($endTime) - ($shift->break_minutes ?? 0);
                            $expectedHours = max(0, $shiftDurationMinutes / 60);
                        }
                        
                        $extraHours = max(0.0, (float)$attendance->total_work_hours - $expectedHours);
                        if ($extraHours > 0) {
                            $overtimeRepo = app(\App\Domains\HRMS\Repositories\OvertimeRequestRepositoryInterface::class);
                            $overtimeRepo->processAutoOvertime($employee, $dateStr, $extraHours);
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to process auto overtime on attendance saved', [
                        'attendance_id' => $attendance->id,
                        'error'         => $e->getMessage()
                    ]);
                }
            }
        });
    }
}
