<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMaster extends BaseModel
{
    protected $table = 'document_masters';

    protected $fillable = [
        'tenant_id',
        'document_category_id',
        'name',
        'code',
        'description',
        'is_required',
        'upload_responsibility',
        'approval_required',
        'expiry_applicable',
        'reminder_days_before',
        'employee_can_view',
        'employee_can_download',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'approval_required' => 'boolean',
        'expiry_applicable' => 'boolean',
        'reminder_days_before' => 'integer',
        'employee_can_view' => 'boolean',
        'employee_can_download' => 'boolean',
    ];

    /**
     * Get the category that this document master belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }
}
