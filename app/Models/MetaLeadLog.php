<?php

namespace App\Models;

use App\Domains\CRM\Models\Lead;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaLeadLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'meta_lead_logs';

    protected $fillable = [
        'tenant_id',
        'meta_configuration_id',
        'leadgen_id',
        'page_id',
        'form_id',
        'ad_id',
        'adgroup_id',
        'campaign_id',
        'full_name',
        'phone_number',
        'email',
        'raw_payload',
        'extracted_data',
        'crm_lead_id',
        'status',
        'error_message',
        'lead_created_time',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'extracted_data' => 'array',
        'lead_created_time' => 'datetime',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(MetaConfiguration::class, 'meta_configuration_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'crm_lead_id');
    }
}
