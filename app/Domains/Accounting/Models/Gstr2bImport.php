<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded GSTR-2B JSON (one return period of one GSTIN / company).
 * Tenant-scoped via BaseModel; filter by company explicitly, like GstReturnFiling.
 */
class Gstr2bImport extends BaseModel
{
    protected $table = 'gstr2b_imports';

    protected $fillable = [
        'tenant_id', 'company_id', 'return_period', 'gstin', 'generated_on', 'file_name',
        'line_count', 'summary', 'matched_at', 'imported_by',
    ];

    protected $casts = [
        'generated_on' => 'date',
        'matched_at' => 'datetime',
        'summary' => 'array',
        'line_count' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(Gstr2bLine::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by')->withoutGlobalScope('tenant');
    }

    /** "092026" → "Sep 2026". */
    public function periodLabel(): string
    {
        $month = (int) substr($this->return_period, 0, 2);
        $year = substr($this->return_period, 2);

        return ($month >= 1 && $month <= 12 ? date('M', mktime(0, 0, 0, $month, 1)) : $this->return_period) . ' ' . $year;
    }
}
