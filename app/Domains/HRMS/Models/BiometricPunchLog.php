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
            if (!empty($log->employee_id) && !empty($log->punch_time)) {
                $punchDate = $log->punch_time instanceof \Carbon\CarbonInterface
                    ? $log->punch_time->toDateString()
                    : \Carbon\Carbon::parse($log->punch_time)->toDateString();

                \App\Domains\HRMS\Jobs\ProcessBiometricAttendance::dispatch(
                    $log->employee_id,
                    $punchDate
                )->afterResponse();
            }
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
