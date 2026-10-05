<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upload status of one invoice / credit note in a GST return, with the exact
 * entry that was reported so later edits are noticed and deletions can be sent.
 */
class GstReturnDocument extends BaseModel
{
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_DELETE_REQUESTED = 'delete_requested';
    public const STATUS_DELETED = 'deleted';

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_CREDIT_NOTE = 'credit_note';

    protected $table = 'gst_return_documents';

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'return_type', 'return_period', 'document_type', 'document_id',
        'document_number', 'section', 'status', 'fingerprint', 'payload', 'gst_return_filing_id', 'uploaded_at', 'uploaded_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function filing(): BelongsTo
    {
        return $this->belongsTo(GstReturnFiling::class, 'gst_return_filing_id');
    }

    public function key(): string
    {
        return $this->document_type . ':' . $this->document_id;
    }
}
