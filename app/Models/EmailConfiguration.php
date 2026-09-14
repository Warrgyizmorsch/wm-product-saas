<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailConfiguration extends Model
{
    protected $table = 'email_configurations';

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id',
        'name', 'email_address', 'from_name', 'driver', 'host', 'port', 'encryption',
        'username', 'password', 'incoming_protocol', 'incoming_host', 'incoming_port',
        'incoming_encryption', 'incoming_username', 'incoming_password',
        'settings', 'is_default', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'port' => 'integer',
        'incoming_port' => 'integer',
        'sort_order' => 'integer',
        'password' => 'encrypted',
        'incoming_password' => 'encrypted',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Branch::class, 'branch_id');
    }

    public function scopeForCurrentContext($query)
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();

        return $query->where('tenant_id', $tenantId)
            ->where(function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)->orWhereNull('company_id');
                } else {
                    $q->whereNull('company_id');
                }
            })
            ->where(function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'email_configuration_id');
    }
}
