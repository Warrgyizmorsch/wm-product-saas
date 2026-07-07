<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionCapa extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_capas';

    protected $fillable = [
        'tenant_id',
        'capa_number',
        'ncr_id',
        'status',
        'root_cause_category',
        'rca_analysis_json',
        'corrective_action',
        'preventive_action',
        'action_owner_id',
        'target_date',
        'verification_notes',
        'effectiveness_review',
        'esignature_closed',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'rca_analysis_json' => 'array',
        'target_date'       => 'date',
        'closed_at'         => 'datetime',
    ];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(ProductionNcr::class, 'ncr_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_owner_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
