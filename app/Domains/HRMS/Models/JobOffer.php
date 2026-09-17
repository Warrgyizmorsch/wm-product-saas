<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOffer extends BaseModel
{
    protected $table = 'job_offers';

    protected $fillable = [
        'tenant_id',
        'application_id',
        'offer_code',
        'offered_designation_id',
        'offered_department_id',
        'document_template_id',
        'offered_annual_ctc',
        'joining_date',
        'offer_letter_notes',
        'offer_letter_content',
        'status',
        'accepted_at',
        'converted_employee_id',
    ];

    protected $casts = [
        'offered_annual_ctc' => 'decimal:2',
        'joining_date' => 'date',
        'accepted_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'offered_designation_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'offered_department_id');
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function convertedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'converted_employee_id');
    }
}
