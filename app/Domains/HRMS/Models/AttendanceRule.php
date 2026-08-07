<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class AttendanceRule extends BaseModel
{
    protected $table = 'attendance_rules';

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
        'status' => 'boolean',
    ];

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
}
