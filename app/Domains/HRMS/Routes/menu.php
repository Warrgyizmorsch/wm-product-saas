<?php

// HRMS sidebar entries. See App\Core\Navigation\MenuRegistry.

use App\Domains\HRMS\Models\AttendanceRule;

// HR administrators: anyone who can manage HR settings or approve leave.
$hrAdmin = ['hr.settings.manage', 'hrms.leave_requests.approve'];

// Self-service HR screens (own leave/attendance/payslip/etc.) have no
// server-side permission of their own — they're for employees managing their
// own record, not an admin capability. Gated on the explicit
// hrms.self_service.use grant (seeded to every working-staff role in
// RbacSeeder) OR an HR-admin permission, rather than left open to every
// authenticated user — otherwise an account with no HRMS grant at all (e.g.
// an Accountant) would see the whole HRMS module just because these entries
// carried no permission key of their own. Deliberately NOT keyed off having a
// linked Employee row: every user here tends to have one for basic profile
// data, which would make the permission gate meaningless.
$selfService = [...$hrAdmin, 'hrms.self_service.use', 'hrms.employees.view'];

return [
    [
        'section' => 'hrms', 'order' => 10, 'permission' => $selfService,
        'label' => 'HRMS Dashboard', 'icon' => 'feather-home', 'route' => 'hrms.dashboard',
    ],
    [
        'section' => 'hrms', 'order' => 20,
        'label' => 'HRMS Masters', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'Org Structure', 'route' => 'hrms.org.index', 'permission' => ['hrms.org.manage', 'hr.settings.manage']],
            ['label' => 'Salary Structure', 'route' => 'hrms.salary-structure.index', 'permission' => 'hrms.salary_structures.view'],
            ['label' => 'Leave Structure', 'route' => 'hrms.leave-structure.index', 'permission' => ['hrms.leave_structures.manage', 'hr.settings.manage']],
            ['label' => 'Shift Roster', 'route' => 'hrms.roster.index', 'permission' => ['hrms.rosters.view', 'hr.settings.manage']],
            ['label' => 'Penalization Policy', 'route' => 'hrms.penalization-policy.index', 'permission' => ['hrms.penalization_policies.manage', 'hr.settings.manage']],
            [
                'label' => 'Biometric Devices', 'route' => 'hrms.biometric-devices.index',
                'permission' => ['hrms.biometric_devices.view', 'hrms.biometric_devices.manage', 'hr.settings.manage'],
                // Only for tenants that clock attendance through office biometrics.
                'when' => fn ($user, $tenant) => AttendanceRule::where('office_biometric', true)
                    ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
                    ->exists(),
            ],
            ['label' => 'Asset Management', 'route' => 'hrms.assets.index', 'permission' => 'hrms.assets.view'],
            ['label' => 'Document Master', 'route' => 'hrms.documents-master.index', 'permission' => ['hrms.document_templates.manage', 'hrms.documents.manage', 'hr.settings.manage']],
            ['label' => 'Holiday Calendar', 'route' => 'hrms.holidays.index', 'permission' => ['hrms.holiday_calendar.manage', 'hr.settings.manage']],
            ['label' => 'Expense Master', 'route' => 'hrms.expense-policy.index', 'permission' => ['hrms.expense_policies.manage', 'hr.settings.manage']],
            ['label' => 'Offboarding Policies', 'route' => 'hrms.offboarding-policies.index', 'permission' => ['hrms.exit_policies.manage', 'hr.settings.manage']],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 30, 'permission' => [...$hrAdmin, 'hrms.employees.view', 'hrms.employees.manage', 'hr.employees.manage'],
        'label' => 'Employees', 'icon' => 'feather-users', 'route' => 'hrms.employees.index',
    ],
    [
        'section' => 'hrms', 'order' => 35,
        'label' => 'Employee Lifecycle', 'icon' => 'feather-user-check',
        'children' => [
            ['label' => 'Probation', 'route' => 'hrms.probation.index', 'permission' => ['hrms.employees.view', 'hrms.probation.manage', ...$hrAdmin]],
            ['label' => 'Employee Exits', 'route' => 'hrms.exits.index', 'permission' => ['hrms.employee_exits.view', ...$hrAdmin]],
            ['label' => 'Profile Edit Requests', 'route' => 'hrms.employees.profile-requests.index', 'permission' => ['hrms.employees.view', ...$hrAdmin]],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 40, 'permission' => [...$hrAdmin, 'hrms.documents.view', 'hrms.documents.manage'],
        'label' => 'Documents', 'icon' => 'feather-file-text', 'route' => 'hrms.documents.index',
    ],
    [
        'section' => 'hrms', 'order' => 50,
        'label' => 'Assets', 'icon' => 'feather-package',
        'children' => [
            ['label' => 'Employees Assets', 'route' => 'hrms.assets-module.index', 'permission' => [...$hrAdmin, 'hrms.assets.view', 'hrms.assets.manage']],
            ['label' => 'My Assets', 'route' => 'hrms.assets-module.my-assets', 'permission' => $selfService],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 60,
        'label' => 'Attendance', 'icon' => 'feather-clock',
        'children' => [
            ['label' => 'Employees Attendance', 'route' => 'hrms.attendance.index', 'permission' => [...$hrAdmin, 'hrms.attendance.view', 'hrms.attendance.manage', 'hr.attendance.manage']],
            ['label' => 'My Attendance', 'route' => 'hrms.attendance.myAttendance', 'permission' => $selfService],
        ],
    ],
    ['section' => 'hrms', 'order' => 70, 'label' => 'Leave', 'icon' => 'feather-calendar', 'route' => 'hrms.leaves.index', 'permission' => $selfService],
    ['section' => 'hrms', 'order' => 80, 'label' => 'WFH', 'icon' => 'feather-home', 'route' => 'hrms.wfh.index', 'permission' => $selfService],
    ['section' => 'hrms', 'order' => 90, 'label' => 'Shift & Overtime', 'icon' => 'feather-activity', 'route' => 'hrms.shift-overtime.index', 'permission' => $selfService],
    ['section' => 'hrms', 'order' => 100, 'label' => 'Travel & Expenses', 'icon' => 'feather-navigation', 'route' => 'hrms.travel-expense.index', 'permission' => $selfService],
    ['section' => 'hrms', 'order' => 105, 'label' => 'KRA & KPI Performance', 'icon' => 'feather-target', 'route' => 'hrms.kra-kpi.index', 'permission' => $selfService],
    [
        'section' => 'hrms', 'order' => 110, 'permission' => [...$hrAdmin, 'hrms.pip.manage', 'hrms.performance.manage'],
        'label' => 'PIP (Performance)', 'icon' => 'feather-trending-up', 'route' => 'hrms.pip.index',
    ],
    ['section' => 'hrms', 'order' => 120, 'label' => 'Broadcasts', 'icon' => 'feather-radio', 'route' => 'hrms.broadcasts.index', 'permission' => $selfService],
    [
        'section' => 'hrms', 'order' => 125, 'permission' => ['hrms.recruitment.view', 'hrms.recruitment.manage', 'hr.settings.manage'],
        'label' => 'Recruitment', 'icon' => 'feather-user-check', 'route' => 'hrms.recruitment.index',
    ],
    [
        'section' => 'hrms', 'order' => 130,
        'label' => 'Helpdesk', 'icon' => 'feather-life-buoy',
        'children' => [
            ['label' => 'Tickets', 'route' => 'hrms.helpdesk.tickets.index', 'permission' => $selfService],
            ['label' => 'Categories', 'route' => 'hrms.helpdesk.categories.index', 'permission' => ['hrms.helpdesk.manage', 'hr.settings.manage']],
            ['label' => 'Knowledge Base', 'route' => 'hrms.helpdesk.kb.index', 'permission' => ['hrms.helpdesk.manage', 'hr.settings.manage']],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 140,
        'label' => 'Payroll', 'icon' => 'feather-dollar-sign',
        'children' => [
            ['label' => 'Payroll Processing', 'route' => 'hrms.payroll.index', 'permission' => [...$hrAdmin, 'hrms.payroll.manage', 'hrms.payroll_runs.view', 'hrms.payroll_runs.create', 'hr.payroll.manage']],
            ['label' => 'My Payslips', 'route' => 'hrms.payroll.mySalary', 'permission' => $selfService],
        ],
    ],
];
