<?php

namespace App\Domains\Accounting\FixedAssets\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\Asset;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends BaseModel
{
    use Loggable;

    protected $table = 'asset_disposals';

    public const TYPE_SALE = 'sale';
    public const TYPE_SCRAP = 'scrap';
    public const TYPE_LOST = 'lost';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_id',
        'disposal_type',
        'disposal_date',
        'original_cost',
        'accumulated_depreciation_at_disposal',
        'net_book_value',
        'sale_proceeds',
        'tax_amount',
        'gain_loss_amount',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'journal_id',
        'remarks',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'original_cost' => 'decimal:2',
        'accumulated_depreciation_at_disposal' => 'decimal:2',
        'net_book_value' => 'decimal:2',
        'sale_proceeds' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gain_loss_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
