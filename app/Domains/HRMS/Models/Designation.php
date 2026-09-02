<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class Designation extends BaseModel
{
    protected $fillable = [
        'department_id',
        'name',
        'level',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Designation belongs to a Department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Designation has many Employees.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }
}
