<?php

namespace App\Domains\Accounting\FixedAssets\Models;

use App\Core\Database\BaseModel;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\Asset;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationSchedule extends BaseModel
{
    use Loggable;

    protected $table = 'asset_depreciation_schedules';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_id',
        'period_year',
        'period_month',
        'period_start_date',
        'period_end_date',
        'opening_book_value',
        'depreciation_amount',
        'closing_book_value',
        'method',
        'status',
        'generated_by',
        'reviewed_by',
        'approved_by',
        'posted_by',
        'generated_at',
        'reviewed_at',
        'approved_at',
        'posted_at',
        'journal_id',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'opening_book_value' => 'decimal:2',
        'depreciation_amount' => 'decimal:2',
        'closing_book_value' => 'decimal:2',
        'generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
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
