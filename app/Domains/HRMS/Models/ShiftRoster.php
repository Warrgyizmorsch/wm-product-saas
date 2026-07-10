<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\Production\Models\ProductionShift;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRoster extends BaseModel
{
    protected $table = 'shift_rosters';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'shift_id',
        'date',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'shift_id');
    }
}
