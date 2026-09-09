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
        'company_name',
        'email',
        'phone',
        'gstin',
        'status',
        'billing_address',
        'shipping_address',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($customer) {
            if (!$customer->crmAccount()->exists()) {
                CrmAccount::create([
                    'tenant_id'        => $customer->tenant_id,
                    'company_id'       => $customer->company_id,
                    'branch_id'        => $customer->branch_id,
                    'customer_id'      => $customer->id,
                    'name'             => $customer->name,
                    'email'            => $customer->email,
                    'phone'            => $customer->phone,
                    'gstin'            => $customer->gstin,
                    'billing_address'  => $customer->billing_address ?? null,
                    'shipping_address' => $customer->shipping_address ?? null,
                    'status'           => strtolower($customer->status ?: 'active') === 'active' ? 'active' : 'inactive',
                    'owner_id'         => auth()->id() ?? 1,
                ]);
            }
        });
    }

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
