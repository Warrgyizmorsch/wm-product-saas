<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'website',
        'subscription_plan',
        'status',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }
}
