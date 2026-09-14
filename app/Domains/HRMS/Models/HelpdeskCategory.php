<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskCategory extends BaseModel
{
    protected $table = 'helpdesk_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'default_agent_id',
        'is_confidential',
        'default_sla_hours',
        'is_active',
    ];

    protected $casts = [
        'is_confidential'   => 'boolean',
        'is_active'         => 'boolean',
        'default_sla_hours' => 'integer',
    ];

    public function defaultAgent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'default_agent_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class, 'category_id');
    }

    public function kbArticles(): HasMany
    {
        return $this->hasMany(HelpdeskKbArticle::class, 'category_id');
    }
}
