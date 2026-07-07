<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionCalendar extends BaseModel
{
    use HasFactory;

    protected $table = 'production_calendars';

    protected $fillable = [
        'tenant_id',
        'name',
        'working_days',
        'is_default',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_default'   => 'boolean',
    ];

    public function holidays(): HasMany
    {
        return $this->hasMany(ProductionCalendarHoliday::class, 'production_calendar_id');
    }
}
