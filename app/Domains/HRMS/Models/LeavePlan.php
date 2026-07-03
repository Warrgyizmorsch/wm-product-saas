<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePlan extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'effective_from',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'effective_from' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function types()
    {
        return $this->hasMany(LeaveType::class, 'leave_plan_id');
    }
}
