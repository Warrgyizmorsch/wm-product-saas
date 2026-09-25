<?php

// Dashboard widgets for HRMS and Common dashboards. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;

return [
    // --- 4 Top KPI Summary Cards ---
    [
        'key' => 'hrms.kpi_total_employees', 'title' => 'Total Employees KPI', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-users', 'w' => 3, 'h' => 3, 'min_w' => 2, 'min_h' => 2, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Workforce headcount summary.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getKpiData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.kpi-total-employees', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.kpi_today_attendance', 'title' => 'Today\'s Attendance KPI', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-check-circle', 'w' => 3, 'h' => 3, 'min_w' => 2, 'min_h' => 2, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Today\'s attendance rate and counts.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getKpiData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.kpi-today-attendance', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.kpi_pending_approvals', 'title' => 'Pending Approvals KPI', 'module' => 'hrms', 'permission' => 'hrms.leave_requests.view',
        'type' => 'html', 'icon' => 'feather-inbox', 'w' => 3, 'h' => 3, 'min_w' => 2, 'min_h' => 2, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Pending inbox count summary.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getKpiData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.kpi-pending-approvals', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.kpi_probation_exits', 'title' => 'Probation & Exits KPI', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-activity', 'w' => 3, 'h' => 3, 'min_w' => 2, 'min_h' => 2, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Employee lifecycle watch count summary.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getKpiData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.kpi-probation-exits', $data)->render(), 'charts' => []];
        },
    ],

    // --- Main Dashboard Cards ---
    [
        'key' => 'hrms.web_punch', 'title' => 'Web Punch Station', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-clock', 'w' => 8, 'h' => 5, 'min_w' => 6, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Live Web Clock In/Out, Break actions and 7-day mini attendance log.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getWebPunchData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.web-punch', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.assigned_leave_plan', 'title' => 'Assigned Leave Plan', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-calendar', 'w' => 4, 'h' => 5, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Assigned Leave Plan quotas, balances and Apply/Encashment buttons.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getLeaveBalancesData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.assigned-leave-plan', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.pending_approvals', 'title' => 'Pending Approvals Action Center', 'module' => 'hrms', 'permission' => 'hrms.leave_requests.view',
        'type' => 'html', 'icon' => 'feather-inbox', 'w' => 8, 'h' => 6, 'min_w' => 6, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Pending approvals table for Leaves, WFH, Punches & Expenses.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getApprovalsData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.pending-approvals', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.my_shift_details', 'title' => 'Current Shift Details', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-calendar', 'w' => 4, 'h' => 5, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Current shift details, weekly pattern and Shift Change/Overtime buttons.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getShiftData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.my-shift-details', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.recent_late_arrivals', 'title' => 'Late Arrivals (Last 7 Days)', 'module' => 'hrms', 'permission' => 'hrms.attendance.view',
        'type' => 'html', 'icon' => 'feather-clock', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Late arrivals recorded in the last 7 days.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getLateArrivalsData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.recent-late-arrivals', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.unprocessed_penalties', 'title' => 'Unprocessed Penalties', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-alert-triangle', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Unprocessed employee penalties pending action.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getPenaltiesData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.unprocessed-penalties', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.latest_salary_slip', 'title' => 'My Latest Salary Slip', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-file-text', 'w' => 4, 'h' => 3, 'min_w' => 3, 'min_h' => 2, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'View & download latest payslip PDF.',
        'data' => function (WidgetContext $c): array {
            return ['html' => view('modules.hrms.dashboard.widgets.latest-salary-slip')->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.approved_leaves', 'title' => 'Approved Leaves', 'module' => 'hrms', 'permission' => 'hrms.leave_requests.view',
        'type' => 'html', 'icon' => 'feather-check-square', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Upcoming approved leave requests.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getApprovedLeavesData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.approved-leaves', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.upcoming_holidays', 'title' => 'Upcoming Holidays', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-gift', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Upcoming holiday calendar events.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getHolidaysData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.upcoming-holidays', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.celebrations', 'title' => 'Celebrations This Month', 'module' => 'hrms', 'permission' => null,
        'type' => 'html', 'icon' => 'feather-award', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Birthdays and work anniversaries this month.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getCelebrationsData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.celebrations', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.probation_ending_soon', 'title' => 'Probation Ending Soon', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-award', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Employees due for probation review.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getProbationData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.probation-ending-soon', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.active_exits_offboarding', 'title' => 'Active Exits & Offboarding', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-user-x', 'w' => 4, 'h' => 6, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Active exit or clearance cases.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getExitData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.active-exits-offboarding', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.department_headcount', 'title' => 'Department Headcount', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-layers', 'w' => 4, 'h' => 5, 'min_w' => 3, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Department employee distribution percentages.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getDepartmentData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.department-headcount', $data)->render(), 'charts' => []];
        },
    ],
    [
        'key' => 'hrms.new_joinees_spotlight', 'title' => 'New Joinees Spotlight', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'html', 'icon' => 'feather-user-plus', 'w' => 8, 'h' => 5, 'min_w' => 6, 'min_h' => 4, 'chrome' => false, 'cache' => false, 'dashboards' => ['common', 'hrms'],
        'description' => 'Employees joined in the last 30 days.',
        'data' => function (WidgetContext $c): array {
            $controller = app(\App\Domains\HRMS\Controllers\HrmsDashboardController::class);
            $data = $controller->getNewJoineesData($c->user, $c->tenantId);
            return ['html' => view('modules.hrms.dashboard.widgets.new-joinees-spotlight', $data)->render(), 'charts' => []];
        },
    ],
];
