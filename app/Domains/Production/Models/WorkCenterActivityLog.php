<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterActivityLog extends BaseModel
{
    use HasFactory;

    protected $table = 'production_work_center_activity_logs';

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'section_id',
        'user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'description',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
