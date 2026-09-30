<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopAssignment extends BaseModel
{
    protected $table = 'sop_assignments';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'sop_document_id',
        'employee_id',
        'version_assigned',
        'status',
        'is_mandatory',
        'assigned_at',
        'due_date',
        'acknowledged_at',
        'ip_address',
        'user_agent',
        'signature_data',
        'checklist_responses',
        'last_reminded_at',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'assigned_at' => 'datetime',
        'due_date' => 'date',
        'acknowledged_at' => 'datetime',
        'last_reminded_at' => 'datetime',
        'checklist_responses' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(SopDocument::class, 'sop_document_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }
}
