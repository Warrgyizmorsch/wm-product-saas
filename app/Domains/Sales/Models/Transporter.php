<?php

namespace App\Domains\Sales\Models;

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
        'transporter_id',
        'gstin',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'status',
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
