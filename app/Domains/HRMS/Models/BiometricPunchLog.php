<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricPunchLog extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'biometric_device_id',
        'employee_id',
        'punch_time',
        'punch_type',
        'processed',
        'raw_data',
    ];

    protected $casts = [
        'punch_time' => 'datetime',
        'processed' => 'boolean',
        'raw_data' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($log) {
            dispatch(new \App\Domains\HRMS\Jobs\ProcessBiometricAttendance(
                $log->employee_id,
                $log->punch_time->toDateString()
            ));
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
