<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwilioConfiguration extends Model
{
    use HasFactory, BelongsToTenant, BelongsToCompany, BelongsToBranch;

    protected $table = 'twilio_configurations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'account_sid',
        'auth_token',
        'phone_number',
        'twiml_app_sid',
        'record_calls',
        'auto_summarize_ai',
        'gemini_api_key',
        'default_lead_owner_id',
        'default_source',
        'default_priority',
        'is_active',
        'extra_metadata',
    ];

    protected $casts = [
        'record_calls' => 'boolean',
        'auto_summarize_ai' => 'boolean',
        'is_active' => 'boolean',
        'extra_metadata' => 'array',
    ];

    public function defaultLeadOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_lead_owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Branch::class, 'branch_id');
    }

    /**
     * Scope for current tenant, company, branch context.
     */
    public function scopeForCurrentContext($query)
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $query->where('tenant_id', $tenantId);

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query;
    }
}
