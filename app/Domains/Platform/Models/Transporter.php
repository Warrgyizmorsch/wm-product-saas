<?php

namespace App\Domains\Platform\Models;

use App\Domains\Sales\Models\DispatchOrder;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transporter extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'transporters';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'transporter_id',
        'gstin',
        'pan_number',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'status',
        'tds_section',
        'tds_rate',
        'has_194c_declaration',
        'declaration_reference',
        'sac_code',
        'transport_mode',
        'fleet_type',
        'serviceable_zones',
        'bank_name',
        'branch_name',
        'account_name',
        'account_number',
        'ifsc_code',
        'payment_terms',
        'opening_balance',
        'contact_person_name',
        'contact_person_phone',
        'contact_person_email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dispatchOrders(): HasMany
    {
        return $this->hasMany(DispatchOrder::class, 'transporter_id');
    }
}
