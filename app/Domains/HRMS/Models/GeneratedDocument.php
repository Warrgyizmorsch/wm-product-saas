<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends BaseModel
{
    protected $table = 'generated_documents';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_template_id',
        'document_master_id',
        'reference_number',
        'title',
        'rendered_content',
        'file_path',
        'issue_date',
        'generated_by',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function documentMaster(): BelongsTo
    {
        return $this->belongsTo(DocumentMaster::class, 'document_master_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
