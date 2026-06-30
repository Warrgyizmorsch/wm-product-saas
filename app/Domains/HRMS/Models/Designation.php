<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
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
     * Employees having this designation.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
