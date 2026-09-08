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
        'document_master_id',
        'is_signed',
        'requires_signature',
        'signed_at',
        'signed_by_id',
        'signature_ip',
        'signed_file_path',
        'signature_metadata',
    ];

    protected $casts = [
        'expiry_date'         => 'date',
        'has_expiry'          => 'boolean',
        'is_signed'           => 'boolean',
        'requires_signature'  => 'boolean',
        'signed_at'           => 'datetime',
        'signature_metadata'  => 'array',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_id');
    }

    public function documentMaster(): BelongsTo
    {
        return $this->belongsTo(DocumentMaster::class, 'document_master_id');
    }
}
