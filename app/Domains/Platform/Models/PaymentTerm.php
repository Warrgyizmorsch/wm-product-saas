<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, SoftDeletes;

    protected $table = 'payment_terms';

    /** Default masters are seeded without a company/branch and shown in every one. */
    public bool $sharedAcrossCompanies = true;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'code',
        'due_days',
        'discount_days',
        'discount_percentage',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_days' => 'integer',
        'discount_days' => 'integer',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDisplayNameAttribute(): string
    {
        $codeKey = 'crm.payment_term_' . strtolower(str_replace(['-', ' ', '/'], '_', $this->code ?? ''));
        if (\Illuminate\Support\Facades\Lang::has($codeKey)) {
            return __($codeKey);
        }

        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $this->name ?? ''));
        $nameKey = 'crm.payment_term_' . trim($cleanName, '_');
        if (\Illuminate\Support\Facades\Lang::has($nameKey)) {
            return __($nameKey);
        }

        if (stripos($this->name ?? '', 'Immediate') !== false || stripos($this->code ?? '', 'RECEIPT') !== false) {
            return __('crm.payment_term_due_receipt');
        }

        return $this->name ?? '';
    }

    public static function getLabel(?string $term): string
    {
        if (empty($term)) {
            return __('crm.immediate_payment');
        }

        $termTrimmed = trim($term);

        // Check if there is a matching master payment term in DB
        $found = static::where('name', $termTrimmed)->orWhere('code', $termTrimmed)->first();
        if ($found) {
            return $found->display_name;
        }

        // Direct matching for common term patterns
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $termTrimmed));
        if (in_array($normalized, ['immediatepayment', 'immediate', 'dueonreceipt'])) {
            return __('crm.immediate_payment');
        }
        if (in_array($normalized, ['net15', 'net15days', '15days'])) {
            return __('crm.payment_term_net15');
        }
        if (in_array($normalized, ['net30', 'net30days', '30days'])) {
            return __('crm.payment_term_net30');
        }
        if (in_array($normalized, ['net45', 'net45days', '45days'])) {
            return __('crm.payment_term_net45');
        }
        if (in_array($normalized, ['net60', 'net60days', '60days'])) {
            return __('crm.payment_term_net60');
        }

        return $termTrimmed;
    }
}
