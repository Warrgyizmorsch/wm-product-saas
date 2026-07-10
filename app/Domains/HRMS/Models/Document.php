<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends BaseModel
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'tenant_id',
        'documentable_id',
        'documentable_type',
        'name',
        'description',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'has_expiry',
        'expiry_date',
        'requested_by_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'has_expiry' => 'boolean',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
