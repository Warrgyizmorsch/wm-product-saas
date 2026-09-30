<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopSection extends BaseModel
{
    protected $table = 'sop_sections';

    protected $fillable = [
        'tenant_id',
        'sop_document_id',
        'step_number',
        'title',
        'content',
        'has_checklist',
        'checklist_items',
        'guidelines',
        'attachment_path',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'has_checklist' => 'boolean',
        'checklist_items' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SopDocument::class, 'sop_document_id');
    }
}
