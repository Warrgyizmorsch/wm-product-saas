<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One "adjust bills" action on the Bill Settlement screen: On Account or
 * Advance money matched to open bills. Same-ledger matches only move
 * allocations; an advance on another ledger is moved by a journal.
 */
class BillSettlement extends BaseModel
{
    protected $table = 'bill_settlements';

    protected $fillable = [
        'tenant_id',
        'party_type',
        'party_id',
        'control_account_id',
        'settled_on',
        'amount',
        'note',
        'journal_id',
        'created_by',
    ];

    protected $casts = [
        'settled_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(BillAllocation::class, 'settlement_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'control_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
