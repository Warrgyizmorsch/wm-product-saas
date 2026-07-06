<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'leave_plan_id',
        'name',
        'code',
        'description',
        'type',
        'color',
        'quota',
        'status',
        'rules',
    ];

    protected $casts = [
        'status' => 'boolean',
        'quota' => 'decimal:1',
        'rules' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(LeavePlan::class, 'leave_plan_id');
    }
}
