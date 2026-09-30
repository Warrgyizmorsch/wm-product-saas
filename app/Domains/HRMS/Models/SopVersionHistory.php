<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SopVersionHistory extends BaseModel
{
    protected $table = 'sop_version_histories';

    protected $fillable = [
        'tenant_id',
        'sop_document_id',
        'version',
        'change_type',
        'changes_summary',
        'created_by',
        'snapshot_data',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SopDocument::class, 'sop_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
