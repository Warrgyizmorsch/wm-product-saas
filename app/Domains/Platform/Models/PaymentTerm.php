<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, SoftDeletes;

    protected $table = 'payment_terms';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'due_days',
        'discount_days',
        'discount_percentage',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_days' => 'integer',
        'discount_days' => 'integer',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
