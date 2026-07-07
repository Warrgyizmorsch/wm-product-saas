<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScanLog extends BaseModel
{
    use HasFactory;

    protected $table = 'production_scan_logs';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'scan_type',
        'scanned_by',
        'device_identifier',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
