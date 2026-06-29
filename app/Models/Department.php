<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;

class Department extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'head_employee_id',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Department belongs to a Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Department Head.
     */
    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }
}
