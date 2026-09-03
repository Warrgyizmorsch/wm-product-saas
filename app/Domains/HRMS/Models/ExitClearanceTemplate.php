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
     * Standard default checklist template definition.
     */
    public const DEFAULT_TEMPLATES = [
        [
            'clearance_category' => 'it_assets',
            'category_name' => 'IT & Systems',
            'item_name' => 'Hardware Asset Recovery (Laptop/Accessories)',
            'description' => 'Collect laptop, chargers, monitors, peripherals, and perform physical inspection.',
            'is_mandatory' => true,
            'sort_order' => 1,
        ],
        [
            'clearance_category' => 'it_assets',
            'category_name' => 'IT & Systems',
            'item_name' => 'Email, Slack & ERP System Logins Deactivation',
            'description' => 'Revoke email accounts, SaaS accesses, internal ERP, and Slack/Teams workspaces.',
            'is_mandatory' => true,
            'sort_order' => 2,
        ],
        [
            'clearance_category' => 'it_assets',
            'category_name' => 'IT & Systems',
            'item_name' => 'Cloud Data Backup & File Handover',
            'description' => 'Transfer Google Drive / OneDrive data and ensure code/documentation repository ownership transfer.',
            'is_mandatory' => false,
            'sort_order' => 3,
        ],
        [
            'clearance_category' => 'facilities_admin',
            'category_name' => 'Facilities & Admin',
            'item_name' => 'Company Physical ID Card & Access Badge Handover',
            'description' => 'Collect building biometric card, office entry badge, and visitor tags.',
            'is_mandatory' => true,
            'sort_order' => 4,
        ],
        [
            'clearance_category' => 'facilities_admin',
            'category_name' => 'Facilities & Admin',
            'item_name' => 'Office Keys, Drawer Keys & Parking Tag Handover',
            'description' => 'Return workstation locker keys, cabin keys, and vehicle parking stickers.',
            'is_mandatory' => true,
            'sort_order' => 5,
        ],
        [
            'clearance_category' => 'finance_payroll',
            'category_name' => 'Finance & Payroll',
            'item_name' => 'Reconcile Open Cash Advances & Loan Accounts',
            'description' => 'Check ledger for any outstanding travel advances, salary advances, or company loans.',
            'is_mandatory' => true,
            'sort_order' => 6,
        ],
        [
            'clearance_category' => 'finance_payroll',
            'category_name' => 'Finance & Payroll',
            'item_name' => 'Verify Pending Travel & Expense Reimbursements',
            'description' => 'Approve or reject all pending expense claims before calculating final settlement.',
            'is_mandatory' => true,
            'sort_order' => 7,
        ],
        [
            'clearance_category' => 'finance_payroll',
            'category_name' => 'Finance & Payroll',
            'item_name' => 'Notice Period Shortfall / Buyout Verification',
            'description' => 'Verify if unserved notice days should be recovered or waived.',
            'is_mandatory' => true,
            'sort_order' => 8,
        ],
        [
            'clearance_category' => 'hr_operations',
            'category_name' => 'HR & Operations',
            'item_name' => 'Exit Interview & Feedback Questionnaire Completed',
            'description' => 'Conduct formal exit interview discussion and record feedback notes.',
            'is_mandatory' => false,
            'sort_order' => 9,
        ],
        [
            'clearance_category' => 'hr_operations',
            'category_name' => 'HR & Operations',
            'item_name' => 'PF, Gratuity & Pension Settlement Verification',
            'description' => 'Verify provident fund transfer documentation, gratuity eligibility, and statutory dues.',
            'is_mandatory' => true,
            'sort_order' => 10,
        ],
        [
            'clearance_category' => 'reporting_manager',
            'category_name' => 'Reporting Manager',
            'item_name' => 'Knowledge Transfer (KT) & Task Handover Sign-off',
            'description' => 'Verify comprehensive handover of ongoing projects, documents, and key tasks to team members.',
            'is_mandatory' => true,
            'sort_order' => 11,
        ],
        [
            'clearance_category' => 'reporting_manager',
            'category_name' => 'Reporting Manager',
            'item_name' => 'Client Contacts, Repo & Credentials Handover',
            'description' => 'Handover client contact details, master passwords, project accounts, and repo permissions.',
            'is_mandatory' => true,
            'sort_order' => 12,
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
        $map = [
            'it_assets' => [
                'name' => $categoryName ?: 'IT & Systems',
                'icon' => 'feather-monitor',
                'color' => 'primary',
                'bg' => 'soft-primary',
            ],
            'it' => [
                'name' => $categoryName ?: 'IT & Systems',
                'icon' => 'feather-monitor',
                'color' => 'primary',
                'bg' => 'soft-primary',
            ],
            'facilities_admin' => [
                'name' => $categoryName ?: 'Facilities & Admin',
                'icon' => 'feather-briefcase',
                'color' => 'warning',
                'bg' => 'soft-warning',
            ],
            'admin' => [
                'name' => $categoryName ?: 'Facilities & Admin',
                'icon' => 'feather-briefcase',
                'color' => 'warning',
                'bg' => 'soft-warning',
            ],
            'finance_payroll' => [
                'name' => $categoryName ?: 'Finance & Payroll',
                'icon' => 'feather-dollar-sign',
                'color' => 'success',
                'bg' => 'soft-success',
            ],
            'finance' => [
                'name' => $categoryName ?: 'Finance & Payroll',
                'icon' => 'feather-dollar-sign',
                'color' => 'success',
                'bg' => 'soft-success',
            ],
            'hr_operations' => [
                'name' => $categoryName ?: 'HR & Operations',
                'icon' => 'feather-users',
                'color' => 'info',
                'bg' => 'soft-info',
            ],
            'hr' => [
                'name' => $categoryName ?: 'HR & Operations',
                'icon' => 'feather-users',
                'color' => 'info',
                'bg' => 'soft-info',
            ],
            'reporting_manager' => [
                'name' => $categoryName ?: 'Reporting Manager',
                'icon' => 'feather-user-check',
                'color' => 'purple',
                'bg' => 'soft-purple',
            ],
            'manager' => [
                'name' => $categoryName ?: 'Reporting Manager',
                'icon' => 'feather-user-check',
                'color' => 'purple',
                'bg' => 'soft-purple',
            ],
            'legal_compliance' => [
                'name' => $categoryName ?: 'Legal & Compliance',
                'icon' => 'feather-shield',
                'color' => 'danger',
                'bg' => 'soft-danger',
            ],
        ];

        $entry = $map[$categoryKey] ?? [
            'name' => $categoryName ?: ucwords(str_replace(['_', '-'], ' ', $categoryKey)),
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
