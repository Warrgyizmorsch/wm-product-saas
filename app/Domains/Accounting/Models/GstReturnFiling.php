<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One export / upload action on a GST return (the JSON file it produced is kept). */
class GstReturnFiling extends BaseModel
{
    public const ACTION_EXPORT = 'export_offline';
    public const ACTION_MARK = 'mark_uploaded';

    protected $table = 'gst_return_filings';

    protected $fillable = [
        'tenant_id', 'company_id', 'branch_id', 'return_type', 'return_period', 'gstin', 'action',
        'voucher_count', 'delete_count', 'file_name', 'payload', 'totals', 'created_by',
    ];

    protected $casts = [
        'totals' => 'array',
        'voucher_count' => 'integer',
        'delete_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GstReturnDocument::class);
    }
}
