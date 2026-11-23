<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vendor statutory & compliance documents tracker (FRS 2.3 / Task C-19).
 * Tracks validity, file attachments, and automated expiry alerts for MSME, LDC, ISO, Agreements.
 */
class VendorDocument extends BaseModel
{
    use HasFactory, SoftDeletes, BelongsToCompany, BelongsToBranch;

    protected $table = 'vendor_documents';

    public const TYPE_MSME = 'msme';
    public const TYPE_LDC = 'ldc_form13';
    public const TYPE_ISO = 'iso_cert';
    public const TYPE_GST = 'gst_cert';
    public const TYPE_AGREEMENT = 'agreement';
    public const TYPE_NDA = 'nda';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_MSME => 'MSME / Udyam Certificate',
        self::TYPE_LDC => 'Lower TDS Certificate (Form 13 / LDC)',
        self::TYPE_ISO => 'ISO / Quality Certificate',
        self::TYPE_GST => 'GST Registration Certificate',
        self::TYPE_AGREEMENT => 'Vendor / Rate Agreement',
        self::TYPE_NDA => 'Non-Disclosure Agreement (NDA)',
        self::TYPE_OTHER => 'Other Statutory Document',
    ];

    /** Days before expiry on which a reminder is sent (the alert command reads reminder_days). */
    public const DEFAULT_REMINDER_DAYS = [30, 15, 7];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'vendor_id',
        'document_type',
        'document_name',
        'document_number',
        'file_path',
        'issue_date',
        'expiry_date',
        'reminder_days',
        'alert_history',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'reminder_days' => 'array',
        'alert_history' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute real-time status based on expiry_date.
     */
    public function getComputedStatusAttribute(): string
    {
        if (! $this->expiry_date) {
            return 'valid';
        }

        $now = Carbon::today();
        if ($this->expiry_date->lt($now)) {
            return 'expired';
        }

        if ($this->expiry_date->lte($now->copy()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->lt(Carbon::today());
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        $now = Carbon::today();
        return $this->expiry_date->gte($now) && $this->expiry_date->lte($now->copy()->addDays($days));
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->expiry_date, false);
    }
}
