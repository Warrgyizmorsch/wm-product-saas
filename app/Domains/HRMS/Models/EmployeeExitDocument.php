<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExitDocument extends BaseModel
{
    protected $table = 'employee_exit_documents';

    protected $fillable = [
        'tenant_id',
        'employee_exit_id',
        'employee_id',
        'document_type',
        'reference_number',
        'issue_date',
        'file_path',
        'content_data',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'content_data' => 'array',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class, 'employee_exit_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
