<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitClearanceTemplate extends BaseModel
{
    protected $table = 'exit_clearance_templates';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'clearance_category',
        'category_name',
        'item_name',
        'description',
        'is_mandatory',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Standard default checklist template definition (5 Core Global Checkpoints).
     */
    public const DEFAULT_TEMPLATES = [
        [
            'clearance_category' => 'it_assets',
            'category_name' => 'IT & Systems',
            'item_name' => 'System & Email Access Deactivation',
            'description' => 'Revoke access to corporate email, SSO accounts, internal applications, and company networks.',
            'is_mandatory' => true,
            'sort_order' => 1,
        ],
        [
            'clearance_category' => 'facilities_admin',
            'category_name' => 'Facilities & Admin',
            'item_name' => 'Building Access Badge & Office Keys Handover',
            'description' => 'Collect office access card, building ID badge, cabin/drawer keys, and parking tags.',
            'is_mandatory' => true,
            'sort_order' => 2,
        ],
        [
            'clearance_category' => 'finance_payroll',
            'category_name' => 'Finance & Payroll',
            'item_name' => 'Expense Claims & Financial Dues Clearance',
            'description' => 'Settle pending expense claims, travel advances, company loans, and finalize payroll deductions.',
            'is_mandatory' => true,
            'sort_order' => 3,
        ],
        [
            'clearance_category' => 'hr_operations',
            'category_name' => 'HR & Operations',
            'item_name' => 'Exit Formalities & Benefits De-enrollment',
            'description' => 'Complete exit interview, statutory documentation, and de-enroll from company insurance & benefits.',
            'is_mandatory' => true,
            'sort_order' => 4,
        ],
        [
            'clearance_category' => 'reporting_manager',
            'category_name' => 'Reporting Manager',
            'item_name' => 'Knowledge Transfer & Project Handover',
            'description' => 'Verify complete handover of ongoing tasks, project files, client contacts, and team responsibilities.',
            'is_mandatory' => true,
            'sort_order' => 5,
        ],
    ];

    public static function normalizeCategoryKey(string $key): string
    {
        $aliases = [
            'it' => 'it_assets',
            'admin' => 'facilities_admin',
            'facilities' => 'facilities_admin',
            'finance' => 'finance_payroll',
            'payroll' => 'finance_payroll',
            'hr' => 'hr_operations',
            'manager' => 'reporting_manager',
        ];
        return $aliases[$key] ?? $key;
    }

    /**
     * Standard UI metadata (icons, badges, colors) for known clearance categories.
     */
    public static function getCategoryMetadata(string $categoryKey, ?string $categoryName = null): array
    {
        $categoryKey = self::normalizeCategoryKey($categoryKey);

        $standardCategoryKeys = [
            'it_assets', 'facilities_admin', 'finance_payroll', 'hr_operations',
            'reporting_manager', 'legal_compliance', 'other',
        ];
        $knownEnglishDefaults = [
            'it & systems', 'it assets', 'it assets & equipment', 'it', 'systems',
            'facilities & admin', 'facilities & assets', 'facilities', 'admin',
            'finance & payroll', 'finance', 'payroll', 'accounts',
            'hr & operations', 'hr operations', 'hr', 'human resources',
            'reporting manager', 'manager', 'line manager',
            'legal & compliance', 'legal', 'compliance',
            'other', 'other / miscellaneous', 'miscellaneous', 'general',
        ];

        $categoryNameLower = strtolower(trim((string) $categoryName));
        $isStandardKey = in_array($categoryKey, $standardCategoryKeys, true);
        $shouldUseLocalized = $isStandardKey && ($categoryNameLower === '' || in_array($categoryNameLower, $knownEnglishDefaults, true));
        $transKey = 'hrms.offboarding_policies.dept_' . $categoryKey;
        $localizedDeptName = \Illuminate\Support\Facades\Lang::has($transKey) ? __($transKey) : null;
        $resolvedName = ($shouldUseLocalized && $localizedDeptName) ? $localizedDeptName : ($categoryName ?: ($localizedDeptName ?: ucwords(str_replace(['_', '-'], ' ', $categoryKey))));

        $map = [
            'it_assets' => [
                'name' => $resolvedName,
                'icon' => 'feather-monitor',
                'color' => 'primary',
                'bg' => 'soft-primary',
            ],
            'it' => [
                'name' => $resolvedName,
                'icon' => 'feather-monitor',
                'color' => 'primary',
                'bg' => 'soft-primary',
            ],
            'facilities_admin' => [
                'name' => $resolvedName,
                'icon' => 'feather-briefcase',
                'color' => 'warning',
                'bg' => 'soft-warning',
            ],
            'admin' => [
                'name' => $resolvedName,
                'icon' => 'feather-briefcase',
                'color' => 'warning',
                'bg' => 'soft-warning',
            ],
            'finance_payroll' => [
                'name' => $resolvedName,
                'icon' => 'feather-dollar-sign',
                'color' => 'success',
                'bg' => 'soft-success',
            ],
            'finance' => [
                'name' => $resolvedName,
                'icon' => 'feather-dollar-sign',
                'color' => 'success',
                'bg' => 'soft-success',
            ],
            'hr_operations' => [
                'name' => $resolvedName,
                'icon' => 'feather-users',
                'color' => 'info',
                'bg' => 'soft-info',
            ],
            'hr' => [
                'name' => $resolvedName,
                'icon' => 'feather-users',
                'color' => 'info',
                'bg' => 'soft-info',
            ],
            'reporting_manager' => [
                'name' => $resolvedName,
                'icon' => 'feather-user-check',
                'color' => 'indigo',
                'bg' => 'soft-indigo',
            ],
            'manager' => [
                'name' => $resolvedName,
                'icon' => 'feather-user-check',
                'color' => 'indigo',
                'bg' => 'soft-indigo',
            ],
            'legal_compliance' => [
                'name' => $resolvedName,
                'icon' => 'feather-shield',
                'color' => 'danger',
                'bg' => 'soft-danger',
            ],
            'other' => [
                'name' => $resolvedName,
                'icon' => 'feather-more-horizontal',
                'color' => 'secondary',
                'bg' => 'soft-secondary',
            ],
            'miscellaneous' => [
                'name' => $resolvedName,
                'icon' => 'feather-more-horizontal',
                'color' => 'secondary',
                'bg' => 'soft-secondary',
            ],
        ];

        $entry = $map[$categoryKey] ?? [
            'name' => $resolvedName,
            'icon' => 'feather-check-circle',
            'color' => 'secondary',
            'bg' => 'soft-secondary',
        ];

        $entry['title'] = $entry['name'];
        return $entry;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
