<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;

class BusinessUnit extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'head_employee_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Business Unit belongs to a Company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Business Unit Head.
     */
    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
