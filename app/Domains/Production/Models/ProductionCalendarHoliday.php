<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCalendarHoliday extends BaseModel
{
    use HasFactory;

    protected $table = 'production_calendar_holidays';

    protected $fillable = [
        'tenant_id',
        'production_calendar_id',
        'name',
        'holiday_date',
        'holiday_type',
        'description',
        'is_full_day',
        'start_time',
        'end_time',
        'active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_full_day'  => 'boolean',
        'active'       => 'boolean',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ProductionCalendar::class, 'production_calendar_id');
    }
}
