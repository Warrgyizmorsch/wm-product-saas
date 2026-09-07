<?php

namespace App\Domains\Accounting\FixedAssets\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\Asset;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRevaluation extends BaseModel
{
    use Loggable;

    protected $table = 'asset_revaluations';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_id',
        'revaluation_date',
        'previous_book_value',
        'revalued_amount',
        'revaluation_surplus_deficit',
        'revised_useful_life_months',
        'revised_residual_value',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'journal_id',
    ];

    protected $casts = [
        'revaluation_date' => 'date',
        'previous_book_value' => 'decimal:2',
        'revalued_amount' => 'decimal:2',
        'revaluation_surplus_deficit' => 'decimal:2',
        'revised_residual_value' => 'decimal:2',
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
