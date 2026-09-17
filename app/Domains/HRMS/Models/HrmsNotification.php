<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmsNotification extends BaseModel
{
    protected $table = 'hrms_notifications';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        'user_id',
        'employee_id',
        'type',
        'title',
        'message',
        'action_url',
        'icon_class',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
