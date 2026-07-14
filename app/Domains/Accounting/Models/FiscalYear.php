<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends BaseModel
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'accounting_fiscal_years';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
