<?php

namespace App\Models;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GstConfiguration extends Model
{
    protected $table = 'gst_configurations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'provider',
        'environment',
        'auth_type',
        'api_base_url',
        'client_id',
        'client_secret',
        'api_token',
        'gstin_username',
        'gstin_password',
        'seller_gstin',
        'legal_name',
        'trade_name',
        'address_line1',
        'address_line2',
        'location',
        'pincode',
        'state_code',
        'contact_email',
        'contact_phone',
        'auto_generate_on_post',
        'is_active',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'tenant_id'             => 'integer',
        'company_id'            => 'integer',
        'branch_id'             => 'integer',
        'auto_generate_on_post' => 'boolean',
        'is_active'             => 'boolean',
        'is_default'            => 'boolean',
        'settings'              => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the active GST configuration for current tenant, company, and branch context.
     */
    public static function getForCurrentContext(?int $companyId = null, ?int $branchId = null): ?self
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = $companyId ?? current_company_id();
        $branchId = $branchId ?? current_branch_id();

        $query = static::where('tenant_id', $tenantId)->where('is_active', true);

        // 1. Try exact branch match
        if ($branchId) {
            $branchConfig = (clone $query)->where('branch_id', $branchId)->first();
            if ($branchConfig) {
                return $branchConfig;
            }
        }

        // 2. Try company match
        if ($companyId) {
            $companyConfig = (clone $query)->where('company_id', $companyId)->whereNull('branch_id')->first();
            if ($companyConfig) {
                return $companyConfig;
            }
        }

        // 3. Try default or first tenant-level config
        return (clone $query)->whereNull('company_id')->whereNull('branch_id')->orderByDesc('is_default')->first()
            ?: (clone $query)->orderByDesc('is_default')->first();
    }
}
