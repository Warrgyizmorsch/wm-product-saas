<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;

class Customer extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'email',
        'phone',
        'gstin',
        'status',
    ];

    public function crmAccount()
    {
        return $this->hasOne(CrmAccount::class, 'customer_id');
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'customer_id');
    }
}
