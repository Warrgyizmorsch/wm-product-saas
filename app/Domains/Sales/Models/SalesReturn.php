<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected ?string $rbacPermission = 'sales.returns.view';

    public function applyRbacScope(Builder $builder, User $user): void
    {
        $builder->where(function (Builder $sub) use ($user) {
            $sub->whereHas('salesOrder', fn($soq) => $soq->where('sales_orders.sales_person_id', $user->id))
                ->orWhereHas('customer.crmAccount', fn($caq) => $caq->where('crm_accounts.owner_id', $user->id));
        });
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'sales_order_id',
        'material_requirement_id',
        'invoice_id',
        'return_number',
        'return_date',
        'status',
        'total_amount',
        'total_refund_amount',
        'reason'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class, 'material_requirement_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
