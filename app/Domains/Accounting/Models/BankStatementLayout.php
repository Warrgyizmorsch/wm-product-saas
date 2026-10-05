<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where the header row and each column sit in one bank's statement export,
 * remembered per bank account so the next file in the same format imports
 * without asking. See BankStatementLayoutDetector.
 */
class BankStatementLayout extends BaseModel
{
    public const SOURCE_DETECTED = 'detected';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'bank_statement_layouts';

    protected $fillable = [
        'tenant_id',
        'chart_of_account_id',
        'name',
        'signature',
        'header_row',
        'column_map',
        'header_cells',
        'source',
        'created_by',
        'last_used_at',
    ];

    protected $casts = [
        'column_map' => 'array',
        'header_cells' => 'array',
        'header_row' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
