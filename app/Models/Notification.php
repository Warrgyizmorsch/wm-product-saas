<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Notification extends BaseModel
{
    protected $table = 'notifications';

    public function getTable()
    {
        if (parent::getTable() === 'notifications' && !Schema::hasTable('notifications')) {
            if (Schema::hasTable('system_notifications')) {
                return 'system_notifications';
            }
            if (Schema::hasTable('hrms_notifications')) {
                return 'hrms_notifications';
            }
        }
        return parent::getTable();
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        'user_id',
        'employee_id',
        'module',
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

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', strtolower($module));
    }
}
