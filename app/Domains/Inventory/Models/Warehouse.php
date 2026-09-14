<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'warehouses';

    public const TYPE_STANDARD = 'standard';
    public const TYPE_SUBCONTRACTOR = 'subcontractor';
    public const TYPE_VIRTUAL = 'virtual';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'type',
        'vendor_id',
        'status',
        'address',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Vendor::class, 'vendor_id');
    }

    /**
     * Ensure a default warehouse exists for the given tenant.
     * Creates 'Main Warehouse' if no warehouse exists for the tenant.
     */
    public static function ensureDefaultWarehouse(?int $tenantId = null, ?int $companyId = null, ?int $branchId = null): self
    {
        $tenantId = $tenantId ?? (tenant_id() ?? current_tenant_id() ?? 1);

        $warehouse = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if (!$warehouse) {
            $warehouse = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->first();
        }

        if (!$warehouse) {
            $comp = $companyId ?? (company_id() ?? current_company_id());
            if ($comp && !\Illuminate\Support\Facades\DB::table('companies')->where('id', $comp)->exists()) {
                $comp = \Illuminate\Support\Facades\DB::table('companies')->where('tenant_id', $tenantId)->value('id');
            }
            if (!$comp) {
                $comp = \Illuminate\Support\Facades\DB::table('companies')->where('tenant_id', $tenantId)->value('id');
            }

            $br = $branchId ?? (branch_id() ?? current_branch_id());
            if ($br && !\Illuminate\Support\Facades\DB::table('branches')->where('id', $br)->exists()) {
                $br = $comp ? \Illuminate\Support\Facades\DB::table('branches')->where('company_id', $comp)->value('id') : null;
            }
            if (!$br && $comp) {
                $br = \Illuminate\Support\Facades\DB::table('branches')->where('company_id', $comp)->value('id');
            }

            $warehouse = static::create([
                'tenant_id'  => $tenantId,
                'company_id' => $comp ?: null,
                'branch_id'  => $br ?: null,
                'name'       => 'Main Warehouse',
                'code'       => 'MWH',
                'type'       => self::TYPE_STANDARD,
                'status'     => 'active',
                'is_default' => true,
            ]);
        }

        return $warehouse;
    }
}

