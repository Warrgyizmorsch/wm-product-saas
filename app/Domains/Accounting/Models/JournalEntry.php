<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends BaseModel
{
    use HasFactory;

    protected $table = 'journal_entries';

    protected $fillable = [
        'tenant_id',
        'journal_id',
        'chart_of_account_id',
        'debit',
        'credit',
        'description',
    ];

    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
