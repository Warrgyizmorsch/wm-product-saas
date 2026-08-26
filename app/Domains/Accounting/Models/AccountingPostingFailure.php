<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;

class AccountingPostingFailure extends BaseModel
{
    protected $table = 'accounting_posting_failures';

    protected $fillable = [
        'tenant_id',
        'event_class',
        'model_class',
        'model_id',
        'message',
        'occurred_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * The record this failed posting was for (an Invoice, VendorBill,
     * CustomerPayment, etc.) — resolved dynamically since failures can come
     * from any of several source modules.
     */
    public function model(): ?object
    {
        return $this->model_class::withoutGlobalScopes()->find($this->model_id);
    }
}
