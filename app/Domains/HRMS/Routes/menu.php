<?php

// HRMS sidebar entries. See App\Core\Navigation\MenuRegistry.

use App\Domains\HRMS\Models\AttendanceRule;

// HR administrators: anyone who can manage HR settings or approve leave.
$hrAdmin = ['hr.settings.manage', 'hrms.leave_requests.approve'];

return [
    [
        'section' => 'hrms', 'order' => 10,
        'label' => 'HRMS Dashboard', 'icon' => 'feather-home', 'route' => 'hrms.dashboard',
    ],
    [
        'section' => 'hrms', 'order' => 20, 'permission' => $hrAdmin,
        'label' => 'HRMS Masters', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'Org Structure', 'route' => 'hrms.org.index'],
            ['label' => 'Salary Structure', 'route' => 'hrms.salary-structure.index'],
            ['label' => 'Leave Structure', 'route' => 'hrms.leave-structure.index'],
            ['label' => 'Shift Roster', 'route' => 'hrms.roster.index'],
            ['label' => 'Penalization Policy', 'route' => 'hrms.penalization-policy.index'],
            [
                'label' => 'Biometric Devices', 'route' => 'hrms.biometric-devices.index',
                // Only for tenants that clock attendance through office biometrics.
                'when' => fn ($user, $tenant) => AttendanceRule::where('office_biometric', true)
                    ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
                    ->exists(),
            ],
            ['label' => 'Asset Management', 'route' => 'hrms.assets.index'],
            ['label' => 'Document Master', 'route' => 'hrms.documents-master.index'],
            ['label' => 'Holiday Calendar', 'route' => 'hrms.holidays.index'],
            ['label' => 'Expense Policies', 'route' => 'hrms.expense-policy.index'],
            ['label' => 'Expense Categories', 'route' => 'hrms.expense-categories.index', 'permission' => 'hrms.expense_policies.manage'],
            ['label' => 'Offboarding Policies', 'route' => 'hrms.offboarding-policies.index'],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 30, 'permission' => $hrAdmin,
        'label' => 'Employees', 'icon' => 'feather-users', 'route' => 'hrms.employees.index',
    ],
    [
        'section' => 'hrms', 'order' => 35,
        'label' => 'Employee Lifecycle', 'icon' => 'feather-user-check',
        'children' => [
            ['label' => 'Probation', 'route' => 'hrms.probation.index', 'permission' => 'hrms.employees.view'],
            ['label' => 'Employee Exits', 'route' => 'hrms.exits.index', 'permission' => 'hrms.employee_exits.view'],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 40, 'permission' => $hrAdmin,
        'label' => 'Documents', 'icon' => 'feather-file-text', 'route' => 'hrms.documents.index',
    ],
    [
        'section' => 'hrms', 'order' => 50,
        'label' => 'Assets', 'icon' => 'feather-package',
        'children' => [
            ['label' => 'Employees Assets', 'route' => 'hrms.assets-module.index', 'permission' => $hrAdmin],
            ['label' => 'My Assets', 'route' => 'hrms.assets-module.my-assets'],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 60,
        'label' => 'Attendance', 'icon' => 'feather-clock',
        'children' => [
            ['label' => 'Employees Attendance', 'route' => 'hrms.attendance.index', 'permission' => $hrAdmin],
            ['label' => 'My Attendance', 'route' => 'hrms.attendance.myAttendance'],
        ],
    ],
    ['section' => 'hrms', 'order' => 70, 'label' => 'Leave', 'icon' => 'feather-calendar', 'route' => 'hrms.leaves.index'],
    ['section' => 'hrms', 'order' => 80, 'label' => 'WFH', 'icon' => 'feather-home', 'route' => 'hrms.wfh.index'],
    ['section' => 'hrms', 'order' => 90, 'label' => 'Shift & Overtime', 'icon' => 'feather-activity', 'route' => 'hrms.shift-overtime.index'],
    ['section' => 'hrms', 'order' => 100, 'label' => 'Travel & Expenses', 'icon' => 'feather-navigation', 'route' => 'hrms.travel-expense.index'],
    [
        'section' => 'hrms', 'order' => 110, 'permission' => $hrAdmin,
        'label' => 'PIP (Performance)', 'icon' => 'feather-trending-up', 'route' => 'hrms.pip.index',
    ],
    ['section' => 'hrms', 'order' => 120, 'label' => 'Broadcasts', 'icon' => 'feather-radio', 'route' => 'hrms.broadcasts.index'],
    [
        'section' => 'hrms', 'order' => 130,
        'label' => 'Helpdesk', 'icon' => 'feather-life-buoy',
        'children' => [
            ['label' => 'Tickets', 'route' => 'hrms.helpdesk.tickets.index'],
            ['label' => 'Categories', 'route' => 'hrms.helpdesk.categories.index', 'permission' => ['hrms.helpdesk.manage', 'hr.settings.manage']],
            ['label' => 'Knowledge Base', 'route' => 'hrms.helpdesk.kb.index', 'permission' => ['hrms.helpdesk.manage', 'hr.settings.manage']],
        ],
    ],
    [
        'section' => 'hrms', 'order' => 140,
        'label' => 'Payroll', 'icon' => 'feather-dollar-sign',
        'children' => [
            ['label' => 'Payroll Processing', 'route' => 'hrms.payroll.index', 'permission' => $hrAdmin],
            ['label' => 'My Payslips', 'route' => 'hrms.payroll.mySalary'],
        ],
    ],
];
