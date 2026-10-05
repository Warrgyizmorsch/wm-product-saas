<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementUpload extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    /** Stored, waiting for the user to map the header row and columns. */
    public const STATUS_NEEDS_MAPPING = 'needs_mapping';

    protected $table = 'bank_statement_uploads';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'bank_reconciliation_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'status',
        'provider',
        'extracted_count',
        'raw_response',
        'error_message',
        'extracted_by',
        'extracted_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'extracted_at' => 'datetime',
        'extracted_count' => 'integer',
    ];

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'bank_statement_upload_id');
    }

    public function extractedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extracted_by');
    }
}
