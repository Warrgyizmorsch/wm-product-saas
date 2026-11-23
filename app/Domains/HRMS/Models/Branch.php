<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Services\TaxIdentityService;
use Illuminate\Validation\ValidationException;

class Branch extends BaseModel
{
    protected $fillable = [
        'company_id',
        'business_unit_id',
        'name',
        'code',
        'gst_number',
        'gst_state_code',
        'is_head_office',
        'manager_employee_id',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_head_office' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Branch $branch): void {
            if (array_key_exists('gst_number', $branch->attributes)) {
                $normalized = TaxIdentityService::normalizeGstin($branch->attributes['gst_number']);
                $branch->attributes['gst_number'] = $normalized;

                if ($normalized !== null) {
                    if (! TaxIdentityService::isValidGstin($normalized)) {
                        throw ValidationException::withMessages([
                            'gst_number' => 'The gst number must be a valid 15-character Indian GSTIN (e.g. 27AAAAA0000A1Z5).',
                        ]);
                    }

                    $company = $branch->company_id
                        ? Company::withoutGlobalScopes()->find($branch->company_id)
                        : null;

                    if ($company) {
                        $companyPan = TaxIdentityService::normalizePan($company->pan_number)
                            ?: TaxIdentityService::extractPanFromGstin($company->gst_number);
                        $branchPan = TaxIdentityService::extractPanFromGstin($normalized);

                        if ($companyPan && $branchPan && $companyPan !== $branchPan) {
                            throw ValidationException::withMessages([
                                'gst_number' => "Branch GSTIN PAN ({$branchPan}) does not match the company PAN ({$companyPan}). A different PAN belongs to a different legal company.",
                            ]);
                        }
                    }

                    $branch->attributes['gst_state_code'] = TaxIdentityService::extractStateCode($normalized);
                } elseif (empty($branch->attributes['gst_state_code'])) {
                    $branch->attributes['gst_state_code'] = null;
                }
            }

            if ($branch->is_head_office && $branch->company_id) {
                $exists = static::withoutGlobalScopes()
                    ->where('company_id', $branch->company_id)
                    ->where('is_head_office', true)
                    ->when($branch->exists, fn ($q) => $q->where('id', '!=', $branch->id))
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'is_head_office' => 'This company already has a head-office branch. Only one head-office branch is allowed per company.',
                    ]);
                }
            }
        });
    }

    /**
     * Effective GSTIN for this branch: own gst_number if set, else parent company gst_number.
     */
    public function effectiveGstin(): ?string
    {
        $own = TaxIdentityService::normalizeGstin($this->gst_number);
        if ($own) {
            return $own;
        }

        $company = $this->relationLoaded('company') ? $this->company : $this->company()->withoutGlobalScopes()->first();

        return TaxIdentityService::normalizeGstin($company?->gst_number);
    }

    /**
     * Effective 2-digit GST state code: branch gst_state_code, else extracted from effectiveGstin().
     */
    public function effectiveStateCode(): ?string
    {
        if (! empty($this->gst_state_code)) {
            return str_pad((string) $this->gst_state_code, 2, '0', STR_PAD_LEFT);
        }

        return TaxIdentityService::extractStateCode($this->effectiveGstin());
    }

    /**
     * Get the effective timezone: Branch override -> Company default -> App fallback
     */
    public function getTimezoneAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('company') && !empty($this->company?->timezone)) {
            return $this->company->timezone;
        }

        return $this->company?->timezone ?? config('app.timezone', 'Asia/Kolkata');
    }

    /**
     * Branch belongs to a Company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Branch belongs to a Business Unit.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Branch Manager.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
