<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class AttendanceRule extends BaseModel
{
    protected $table = 'attendance_rules';

    /** Return only an active rule applicable to this employee's org scope. */
    public static function forEmployee(?Employee $employee): ?self
    {
        if (!$employee || !$employee->company_id) {
            return null;
        }

        $query = static::query()
            ->where('status', true)
            ->where('company_id', $employee->company_id);

        if ($employee->tenant_id !== null) {
            $query->where(function ($scope) use ($employee) {
                $scope->whereNull('tenant_id')
                    ->orWhere('tenant_id', $employee->tenant_id);
            });
        } else {
            $query->whereNull('tenant_id');
        }

        foreach (['business_unit_id', 'branch_id'] as $scopeColumn) {
            $scopeValue = $employee->{$scopeColumn};
            $query->where(function ($scope) use ($scopeColumn, $scopeValue) {
                if ($scopeValue === null) {
                    $scope->whereNull($scopeColumn);
                } else {
                    $scope->whereNull($scopeColumn)->orWhere($scopeColumn, $scopeValue);
                }
            });
        }

        return $query
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN business_unit_id IS NULL THEN 0 ELSE 1 END DESC')
            ->when($employee->tenant_id !== null, fn ($q) => $q->orderByRaw('CASE WHEN tenant_id = ? THEN 1 ELSE 0 END DESC', [$employee->tenant_id]))
            ->first();
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        
        // Office Settings
        'office_biometric',
        'office_web',
        'office_geofence',
        'office_latitude',
        'office_longitude',
        'office_radius',
        'office_tracking',
        'office_tracking_minutes',

        // WFH Settings
        'wfh_location',
        'wfh_selfie',
        'wfh_geofence',
        'wfh_tracking',
        'wfh_tracking_meters',
        'wfh_tracking_minutes',

        // On-Site Settings
        'site_location',
        'site_selfie',
        'site_geofence',
        'site_tracking',
        'site_tracking_meters',
        'site_tracking_minutes',

        // Advanced Settings
        'pause_tracking_on_break',
        'office_break_geofence',
        'allow_multiple_punches',
        
        'status',
    ];

    protected $casts = [
        'office_biometric' => 'boolean',
        'office_web' => 'boolean',
        'office_geofence' => 'boolean',
        'office_radius' => 'integer',
        'office_tracking' => 'boolean',
        'office_tracking_minutes' => 'integer',
        'wfh_location' => 'boolean',
        'wfh_selfie' => 'boolean',
        'wfh_geofence' => 'boolean',
        'wfh_tracking' => 'boolean',
        'wfh_tracking_meters' => 'integer',
        'wfh_tracking_minutes' => 'integer',
        'site_location' => 'boolean',
        'site_selfie' => 'boolean',
        'site_geofence' => 'boolean',
        'site_tracking' => 'boolean',
        'site_tracking_meters' => 'integer',
        'site_tracking_minutes' => 'integer',
        'pause_tracking_on_break' => 'boolean',
        'office_break_geofence' => 'boolean',
        'allow_multiple_punches' => 'boolean',
        'status' => 'boolean',
    ];

    public function isBreakGeofenceEnabled(): bool
    {
        return (bool) ($this->office_break_geofence ?? false);
    }

    public function shouldPauseTrackingOnBreak(): bool
    {
        return (bool) ($this->pause_tracking_on_break ?? false);
    }

    public function allowsMultiplePunches(): bool
    {
        return (bool) ($this->allow_multiple_punches ?? false);
    }

    /**
     * Rule belongs to a Company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Rule belongs to a Business Unit.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /**
     * Rule belongs to a Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Check if location tracking checkbox is marked in rules for this location type.
     * If unchecked, tracking is completely skipped.
     */
    public function isTrackingEnabled(?string $locationType): bool
    {
        $loc = strtolower(trim((string)$locationType));

        if ($loc === 'wfh') {
            return (bool) $this->wfh_tracking;
        }

        if (in_array($loc, ['onsite', 'on-site', 'site'], true)) {
            return (bool) $this->site_tracking;
        }

        return (bool) ($this->office_tracking ?? false);
    }

    /**
     * Get tracking time period from rules. Returns null if tracking is unchecked/skipped.
     */
    public function getTrackingMinutes(?string $locationType): ?int
    {
        if (!$this->isTrackingEnabled($locationType)) {
            return null; // Skip tracking
        }

        $loc = strtolower(trim((string)$locationType));

        if ($loc === 'wfh') {
            return $this->wfh_tracking_minutes !== null ? (int) $this->wfh_tracking_minutes : null;
        }

        if (in_array($loc, ['onsite', 'on-site', 'site'], true)) {
            return $this->site_tracking_minutes !== null ? (int) $this->site_tracking_minutes : null;
        }

        return $this->office_tracking_minutes !== null ? (int) $this->office_tracking_minutes : null;
    }
}
