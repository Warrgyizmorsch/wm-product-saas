<?php

namespace App\Models;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConfiguration extends Model
{
    protected $table = 'whatsapp_configurations';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'session_key',
        'account_name',
        'phone_number',
        'status',
        'bridge_url',
        'bridge_token',
        'is_default',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'tenant_id'  => 'integer',
        'company_id' => 'integer',
        'branch_id'  => 'integer',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'settings'   => 'array',
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
     * Get or create the WhatsApp configuration record for current tenant, company, and branch context.
     */
    public static function getForCurrentContext(): self
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $query = static::where('tenant_id', $tenantId);

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

        $config = $query->orderByRaw('branch_id IS NOT NULL DESC, company_id IS NOT NULL DESC, is_default DESC')->first();

        if (!$config) {
            $cIdStr = $companyId ?: 1;
            $bIdStr = $branchId ?: 1;
            $sessionKey = sprintf('wm-t%d-c%d-b%d', $tenantId, $cIdStr, $bIdStr);

            $config = static::create([
                'tenant_id'    => $tenantId,
                'company_id'   => $companyId,
                'branch_id'    => $branchId,
                'session_key'  => $sessionKey,
                'bridge_url'   => 'http://127.0.0.1:3210',
                'bridge_token' => 'wm_erp_whatsapp_secret_token_2026',
                'is_active'    => true,
                'is_default'   => true,
                'status'       => 'disconnected',
            ]);
        }

        return $config;
    }
}
