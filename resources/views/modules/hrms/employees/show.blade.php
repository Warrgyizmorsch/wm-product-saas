@extends('layouts.duralux')

@section('title', 'EMPLOYEE PROFILE | SaaS ERP')
@section('page-title', 'Employee Profile')
@section('breadcrumb', 'HRMS / Employees / Profile')

@section('page-actions')
    <x-ui.button href="{{ route('hrms.employees.index') }}" variant="light" icon="feather-arrow-left">
        Back to Registry
    </x-ui.button>
@endsection

@php
    $formatLeaveRuleText = static function (?array $rules): array {
        if (empty($rules)) {
            return [];
        }

        $humanize = static fn ($value) => ucwords(str_replace('_', ' ', (string) $value));
        $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $items = [];

        if (!empty($rules['accrual'])) {
            $accrual = $rules['accrual'];
            $unit = $humanize($accrual['calculate_in'] ?? 'days');
            $quota = ($accrual['quota_type'] ?? 'fixed') === 'unlimited'
                ? 'Unlimited'
                : $formatNumber($accrual['quota_value'] ?? 0) . ' ' . strtolower($unit);
            $rate = $humanize($accrual['rate'] ?? 'immediate');

            $items[] = ['label' => 'Accrual', 'value' => "{$quota} • {$rate}"];

            if (!empty($accrual['limit_carry'])) {
                $items[] = ['label' => 'Max Balance', 'value' => ($accrual['max_accum'] ?? 0) . ' days'];
            }
        }

        if (!empty($rules['application'])) {
            $application = $rules['application'];
            $duration = ($application['min_duration'] ?? 1) . '–' . ($application['max_duration'] ?? 10) . ' days';
            $items[] = ['label' => 'Duration', 'value' => $duration];

            if (!empty($application['apply_in_advance'])) {
                $items[] = ['label' => 'Advance', 'value' => ($application['advance_days'] ?? 0) . ' days'];
            }

            if (!empty($application['require_attachment'])) {
                $items[] = ['label' => 'Attachment', 'value' => 'After ' . ($application['attachment_days'] ?? 0) . ' days'];
            }
        }

        if (!empty($rules['approval'])) {
            $approval = $rules['approval'];
            $workflow = $humanize($approval['workflow_level'] ?? '1_level');

            if (($approval['workflow_level'] ?? null) === 'auto') {
                $items[] = ['label' => 'Approval', 'value' => 'Auto approved'];
            } else {
                $approvers = array_filter([
                    $humanize($approval['first_approver'] ?? ''),
                    ($approval['workflow_level'] ?? '') === '2_level' ? $humanize($approval['second_approver'] ?? '') : null,
                ]);
                $items[] = ['label' => 'Approval', 'value' => $workflow . (!empty($approvers) ? ' • ' . implode(' → ', $approvers) : '')];
            }
        }

        if (!empty($rules['yearend'])) {
            $yearend = $rules['yearend'];
            $action = $humanize($yearend['action'] ?? 'lapse');
            $limit = match ($yearend['action'] ?? null) {
                'carry_forward' => ' • Max ' . ($yearend['max_carry'] ?? 0) . ' days',
                'encash' => ' • Max ' . ($yearend['max_encash'] ?? 0) . ' days',
                default => '',
            };
            $items[] = ['label' => 'Year End', 'value' => $action . $limit];
        }

        if (!empty($rules['probation'])) {
            $probation = $rules['probation'];
            $value = match ($probation['rule'] ?? 'allow') {
                'disallow' => 'Not allowed',
                'allow_after_months' => 'After ' . ($probation['months'] ?? 0) . ' months',
                default => 'Allowed',
            };
            $items[] = ['label' => 'Probation', 'value' => $value];
        }

        if (!empty($rules['notice'])) {
            $notice = $rules['notice'];
            $value = match ($notice['rule'] ?? 'allow') {
                'disallow' => 'Not allowed',
                'special_approval' => 'Special approval',
                default => 'Allowed',
            };
            $items[] = ['label' => 'Notice', 'value' => $value];
        }

        return array_slice($items, 0, 6);
    };
@endphp

@php
    $formatLeaveRuleDetails = static function (?array $rules): array {
        if (empty($rules)) {
            return [];
        }

        $humanize = static fn ($value) => ucwords(str_replace('_', ' ', (string) $value));
        $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $yesNo = static fn ($value) => $value ? 'Yes' : 'No';
        $sections = [];

        if (!empty($rules['accrual'])) {
            $accrual = $rules['accrual'];
            $quota = ($accrual['quota_type'] ?? 'fixed') === 'unlimited'
                ? 'Unlimited'
                : $formatNumber($accrual['quota_value'] ?? 0) . ' ' . strtolower($humanize($accrual['calculate_in'] ?? 'days'));
            $rows = [
                ['label' => 'Calculate In', 'value' => $humanize($accrual['calculate_in'] ?? 'days')],
                ['label' => 'Quota', 'value' => $quota],
                ['label' => 'Accrual Rate', 'value' => $humanize($accrual['rate'] ?? 'immediate')],
                ['label' => 'Limit Max Balance', 'value' => $yesNo(!empty($accrual['limit_carry']))],
            ];

            if (($accrual['rate'] ?? null) === 'attendance') {
                $rows[] = ['label' => 'Attendance Earning', 'value' => ($accrual['attendance_earn'] ?? 1) . ' day per ' . ($accrual['attendance_period'] ?? 20) . ' present days'];
            }

            if (!empty($accrual['limit_carry'])) {
                $rows[] = ['label' => 'Maximum Balance', 'value' => ($accrual['max_accum'] ?? 0) . ' days'];
            }

            $sections[] = ['title' => 'Accrual', 'icon' => 'feather-calendar', 'rows' => $rows];
        }

        if (!empty($rules['application'])) {
            $application = $rules['application'];
            $sections[] = [
                'title' => 'Leave Application',
                'icon' => 'feather-file-text',
                'rows' => [
                    ['label' => 'Apply In Advance', 'value' => $yesNo(!empty($application['apply_in_advance']))],
                    ['label' => 'Advance Days', 'value' => ($application['advance_days'] ?? 0) . ' days'],
                    ['label' => 'Minimum Duration', 'value' => ($application['min_duration'] ?? 1) . ' day(s)'],
                    ['label' => 'Maximum Duration', 'value' => ($application['max_duration'] ?? 10) . ' day(s)'],
                    ['label' => 'Attachment Required', 'value' => $yesNo(!empty($application['require_attachment']))],
                    ['label' => 'Attachment After', 'value' => ($application['attachment_days'] ?? 0) . ' days'],
                ],
            ];
        }

        if (!empty($rules['approval'])) {
            $approval = $rules['approval'];
            $rows = [
                ['label' => 'Workflow', 'value' => ($approval['workflow_level'] ?? null) === 'auto' ? 'Auto Approved' : $humanize($approval['workflow_level'] ?? '1_level')],
            ];

            if (($approval['workflow_level'] ?? null) !== 'auto') {
                $rows[] = ['label' => 'First Approver', 'value' => $humanize($approval['first_approver'] ?? 'reporting_manager')];
                if (($approval['workflow_level'] ?? null) === '2_level') {
                    $rows[] = ['label' => 'Second Approver', 'value' => $humanize($approval['second_approver'] ?? 'hr_manager')];
                }
            }

            $sections[] = ['title' => 'Approval Workflow', 'icon' => 'feather-check-square', 'rows' => $rows];
        }

        if (!empty($rules['yearend'])) {
            $yearend = $rules['yearend'];
            $rows = [
                ['label' => 'Action', 'value' => $humanize($yearend['action'] ?? 'lapse')],
            ];

            if (($yearend['action'] ?? null) === 'carry_forward') {
                $rows[] = ['label' => 'Max Carry Forward', 'value' => ($yearend['max_carry'] ?? 0) . ' days'];
            }

            if (($yearend['action'] ?? null) === 'encash') {
                $rows[] = ['label' => 'Max Encashment', 'value' => ($yearend['max_encash'] ?? 0) . ' days'];
            }

            $sections[] = ['title' => 'Year End Processing', 'icon' => 'feather-refresh-cw', 'rows' => $rows];
        }

        if (!empty($rules['probation'])) {
            $probation = $rules['probation'];
            $value = match ($probation['rule'] ?? 'allow') {
                'disallow' => 'Not allowed during probation',
                'allow_after_months' => 'Allowed after ' . ($probation['months'] ?? 0) . ' months',
                default => 'Allowed during probation',
            };
            $sections[] = ['title' => 'Probation', 'icon' => 'feather-shield', 'rows' => [['label' => 'Usage Rule', 'value' => $value]]];
        }

        if (!empty($rules['notice'])) {
            $notice = $rules['notice'];
            $value = match ($notice['rule'] ?? 'allow') {
                'disallow' => 'Not allowed during notice period',
                'special_approval' => 'Requires special HR approval',
                default => 'Allowed during notice period',
            };
            $sections[] = ['title' => 'Notice Period', 'icon' => 'feather-alert-triangle', 'rows' => [['label' => 'Usage Rule', 'value' => $value]]];
        }

        return $sections;
    };
@endphp

@php
    $formatLeaveRulePoints = static function (?array $rules): array {
        if (empty($rules)) {
            return [];
        }

        $humanize = static fn ($value) => strtolower(str_replace('_', ' ', (string) $value));
        $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        $sections = [];

        if (!empty($rules['application'])) {
            $application = $rules['application'];
            $points = [];

            if (!empty($application['apply_in_advance'])) {
                $points[] = 'You have to apply for this leave at least ' . ($application['advance_days'] ?? 0) . ' day(s) in advance.';
            } else {
                $points[] = 'You can apply for this leave without an advance-day restriction.';
            }

            $points[] = 'One request can be from ' . ($application['min_duration'] ?? 1) . ' to ' . ($application['max_duration'] ?? 10) . ' day(s).';

            if (!empty($application['require_attachment'])) {
                $points[] = 'You must attach supporting documents when the leave duration is more than ' . ($application['attachment_days'] ?? 0) . ' day(s).';
            }

            $sections[] = ['title' => 'Application Rules', 'icon' => 'feather-file-text', 'points' => $points];
        }

        if (!empty($rules['approval'])) {
            $approval = $rules['approval'];
            $points = [];

            if (($approval['workflow_level'] ?? null) === 'auto') {
                $points[] = 'This leave is approved automatically after submission.';
            } elseif (($approval['workflow_level'] ?? null) === '2_level') {
                $points[] = 'This leave needs two approvals: first by ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . ', then by ' . $humanize($approval['second_approver'] ?? 'hr_manager') . '.';
            } else {
                $points[] = 'This leave needs approval from ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . '.';
            }

            $sections[] = ['title' => 'Approval Rules', 'icon' => 'feather-check-square', 'points' => $points];
        }

        if (!empty($rules['accrual'])) {
            $accrual = $rules['accrual'];
            $points = [];
            $unit = $humanize($accrual['calculate_in'] ?? 'days');

            if (($accrual['quota_type'] ?? 'fixed') === 'unlimited') {
                $points[] = 'This leave has unlimited quota.';
            } else {
                $points[] = 'You get ' . $formatNumber($accrual['quota_value'] ?? 0) . ' ' . $unit . ' of this leave.';
            }

            $rate = $accrual['rate'] ?? 'immediate';
            if ($rate === 'attendance') {
                $points[] = 'Leave is earned based on attendance: ' . ($accrual['attendance_earn'] ?? 1) . ' day for every ' . ($accrual['attendance_period'] ?? 20) . ' present day(s).';
            } elseif ($rate === 'periodic') {
                $points[] = 'Leave is credited periodically as configured in the leave policy.';
            } else {
                $points[] = 'Leave is credited immediately.';
            }

            if (!empty($accrual['limit_carry'])) {
                $points[] = 'Maximum accumulated balance is limited to ' . ($accrual['max_accum'] ?? 0) . ' day(s).';
            }

            $sections[] = ['title' => 'Accrual Rules', 'icon' => 'feather-calendar', 'points' => $points];
        }

        if (!empty($rules['yearend'])) {
            $yearend = $rules['yearend'];
            $points = [];

            if (($yearend['action'] ?? 'lapse') === 'carry_forward') {
                $points[] = 'Unused leave can be carried forward at year end.';
                $points[] = 'Maximum carry-forward limit is ' . ($yearend['max_carry'] ?? 0) . ' day(s).';
            } elseif (($yearend['action'] ?? null) === 'encash') {
                $points[] = 'Unused leave can be encashed at year end.';
                $points[] = 'Maximum encashment limit is ' . ($yearend['max_encash'] ?? 0) . ' day(s).';
            } else {
                $points[] = 'Unused leave lapses at year end.';
            }

            $sections[] = ['title' => 'Year-End Rules', 'icon' => 'feather-refresh-cw', 'points' => $points];
        }

        if (!empty($rules['probation'])) {
            $probation = $rules['probation'];
            $point = match ($probation['rule'] ?? 'allow') {
                'disallow' => 'You cannot apply for this leave during probation.',
                'allow_after_months' => 'You can apply for this leave after completing ' . ($probation['months'] ?? 0) . ' month(s) from joining.',
                default => 'You can apply for this leave during probation.',
            };
            $sections[] = ['title' => 'Probation Rules', 'icon' => 'feather-shield', 'points' => [$point]];
        }

        if (!empty($rules['notice'])) {
            $notice = $rules['notice'];
            $point = match ($notice['rule'] ?? 'allow') {
                'disallow' => 'You cannot apply for this leave during the notice period.',
                'special_approval' => 'You need special HR approval to apply during the notice period.',
                default => 'You can apply for this leave during the notice period.',
            };
            $sections[] = ['title' => 'Notice Period Rules', 'icon' => 'feather-alert-triangle', 'points' => [$point]];
        }

        return $sections;
    };
@endphp

@section('content')
    <style>
        .erp-pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
            padding-left: 0;
            list-style: none;
        }
        .erp-pagination .page-item {
            display: inline-block;
        }
        .erp-pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50% !important;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            cursor: pointer;
            outline: none;
            box-shadow: none;
        }
        .erp-pagination .page-link:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.2);
        }
        .erp-pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .profile-page {
            padding: 24px;
            background-color: #f8fafc;
            min-height: calc(100vh - 120px);
        }

        .profile-header-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            padding: 24px;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.16), rgba(13, 110, 253, 0.04));
            color: var(--bs-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            overflow: hidden;
            border: 4px solid #fff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-label {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-value {
            color: #0f172a;
            font-size: 14px;
            font-weight: 600;
        }

        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background-color: #fff;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02);
            margin-bottom: 24px;
        }

        .card-custom-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-custom-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-nav-custom {
            border-bottom: 2px solid #e2e8f0;
            gap: 8px;
            margin-bottom: 24px;
        }

        .tab-nav-custom .nav-link {
            border: none !important;
            border-bottom: 3px solid transparent !important;
            background: transparent !important;
            color: #64748b !important;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 20px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-nav-custom .nav-link:hover {
            color: var(--bs-primary) !important;
            border-bottom-color: #cbd5e1 !important;
        }

        .tab-nav-custom .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom-color: var(--bs-primary) !important;
            font-weight: 700;
        }

        .leave-rules-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-width: 520px;
        }

        .leave-rule-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            line-height: 1.2;
        }

        .leave-rule-chip strong {
            color: #0f172a;
            font-weight: 700;
        }

        .leave-rule-standard {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 13px;
        }

        .leave-rules-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid rgba(var(--bs-primary-rgb), 0.18);
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            color: var(--bs-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .leave-rules-icon-btn:hover {
            background-color: var(--bs-primary);
            color: #fff;
            box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), 0.22);
        }

        .leave-rule-detail-section {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
            height: 100%;
        }

        .leave-rule-detail-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .leave-rule-detail-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            border-top: 1px dashed #e2e8f0;
            font-size: 13px;
        }

        .leave-rule-detail-row:first-of-type {
            border-top: 0;
        }

        .leave-rule-detail-row span:first-child {
            color: #64748b;
            font-weight: 700;
        }

        .leave-rule-detail-row span:last-child {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        .leave-rule-points {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .leave-rule-point {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #475569;
            font-size: 13px;
            line-height: 1.55;
        }

        .leave-rule-point::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background-color: var(--bs-primary);
            box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.1);
            margin-top: 7px;
            flex: 0 0 auto;
        }
    </style>

    <div class="profile-page">
        @if(session('success'))
            <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                {{ session('success') }}
            </x-ui.alert>
        @endif

        <!-- Profile Header -->
        <div class="profile-header-card mb-4">
            <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                <div class="profile-avatar-large">
                    @if($employee->photo)
                        <img src="{{ asset('storage/' . $employee->photo) }}" alt="{{ $employee->display_name }}">
                    @else
                        {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) ?: 'EM' }}
                    @endif
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <div class="d-flex flex-column flex-md-row align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0">{{ $employee->display_name }}</h3>
                        @if($employee->status)
                            <x-ui.badge variant="success" soft>Active</x-ui.badge>
                        @else
                            <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-muted fs-14 mb-2">{{ $employee->job_title ?: 'No Job Title' }} &bull; {{ $employee->department?->name ?? 'No Department' }}</p>
                    <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3 text-muted fs-12">
                        <span><i class="feather-tag me-1"></i><code class="fs-13 fw-bold">{{ $employee->employee_id }}</code></span>
                        <span><i class="feather-mail me-1"></i>{{ $employee->personal_email ?: 'No Email' }}</span>
                        <span><i class="feather-phone me-1"></i>{{ $employee->personal_mobile_number ?: 'No Mobile' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs tab-nav-custom" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">
                    <i class="feather-user"></i> Profile Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="compensation-tab" data-bs-toggle="tab" data-bs-target="#compensation-pane" type="button" role="tab" aria-controls="compensation-pane" aria-selected="false">
                    <i class="feather-dollar-sign"></i> Compensation & Salary
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves-pane" type="button" role="tab" aria-controls="leaves-pane" aria-selected="false">
                    <i class="feather-calendar"></i> Leaves & Plan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="penalization-tab" data-bs-toggle="tab" data-bs-target="#penalization-pane" type="button" role="tab" aria-controls="penalization-pane" aria-selected="false">
                    <i class="feather-alert-triangle"></i> Attendance & Penalties
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- 1. OVERVIEW TAB -->
            <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                <div class="row">
                    <div class="col-md-6 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-briefcase text-primary"></i> Employment Details</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="info-label">Company</div>
                                        <div class="info-value">{{ $employee->company?->company_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Business Unit</div>
                                        <div class="info-value">{{ $employee->businessUnit?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Branch</div>
                                        <div class="info-value">{{ $employee->branch?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Department</div>
                                        <div class="info-value">{{ $employee->department?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Designation</div>
                                        <div class="info-value">{{ $employee->designation?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Date of Joining</div>
                                        <div class="info-value">{{ $employee->date_of_joining ? $employee->date_of_joining->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Employment Type</div>
                                        <div class="info-value">{{ $employee->employment_type ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Stage</div>
                                        <div class="info-value">{{ $employee->employee_stage ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Reporting Manager</div>
                                        <div class="info-value">{{ $employee->reportingManager?->full_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Work Shift</div>
                                        <div class="info-value">{{ $employee->shift?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Office Email</div>
                                        <div class="info-value">{{ $employee->office_email ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Probation End Date</div>
                                        <div class="info-value">{{ $employee->probation_end_date ? $employee->probation_end_date->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Confirmation Date</div>
                                        <div class="info-value">{{ $employee->confirmation_date ? $employee->confirmation_date->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-user text-primary"></i> Personal Details</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="info-label">Gender</div>
                                        <div class="info-value">{{ $employee->gender }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Marital Status</div>
                                        <div class="info-value">{{ $employee->marital_status ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Blood Group</div>
                                        <div class="info-value">{{ $employee->blood_group ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Diet Preference</div>
                                        <div class="info-value">{{ $employee->diet_preference ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Aadhaar Number</div>
                                        <div class="info-value">{{ $employee->aadhaar_card_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">PAN Number</div>
                                        <div class="info-value">{{ $employee->pan_card_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">Date of Birth</div>
                                        <div class="info-value">{{ $employee->date_of_birth ? $employee->date_of_birth->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-map-pin text-primary"></i> Contact & Address</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Personal Mobile</div>
                                        <div class="info-value">{{ $employee->personal_mobile_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Home Phone</div>
                                        <div class="info-value">{{ $employee->home_phone ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">City / Postal Code</div>
                                        <div class="info-value">{{ $employee->city ?: 'N/A' }} {{ $employee->postal_code ? '(' . $employee->postal_code . ')' : '' }}</div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">Present Address</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->present_address ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">Permanent Address</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->permanent_address ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Emergency Contact Name</div>
                                        <div class="info-value">{{ $employee->emergency_contact_name ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Emergency Contact Number</div>
                                        <div class="info-value">{{ $employee->emergency_contact_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Emergency Contact Relation</div>
                                        <div class="info-value">{{ $employee->emergency_contact_relation ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-credit-card text-primary"></i> Bank Account Details</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Bank Name</div>
                                        <div class="info-value">{{ $employee->bank_name ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">Account Number</div>
                                        <div class="info-value">{{ $employee->account_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">IFSC Code</div>
                                        <div class="info-value">{{ $employee->ifsc_code ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. COMPENSATION & SALARY TAB -->
            <div class="tab-pane fade" id="compensation-pane" role="tabpanel" aria-labelledby="compensation-tab">
                <div class="row g-4">
                    <!-- Left: Slab & Structure Breakdown -->
                    <div class="col-lg-8 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <div>
                                    <h5 class="card-custom-title"><i class="feather-dollar-sign text-primary"></i> Computed Salary Components</h5>
                                    <small class="text-muted d-block mt-1">Calculations based on dynamic slab matching for CTC & Pay Group.</small>
                                </div>
                                @if($salaryStructure)
                                    <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill fs-12">
                                        {{ $salaryStructure->name }}
                                    </span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-3 py-2 rounded-pill fs-12">
                                        No Slab Resolved
                                    </span>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                @if(!$employee->payGroup)
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-alert-circle fs-32 d-block mb-3 text-warning"></i>
                                        <div class="fw-bold mb-1">No Pay Group Assigned</div>
                                        <div>Please edit the employee to assign a Pay Group first.</div>
                                    </div>
                                @elseif(!$salaryStructure)
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-alert-octagon fs-32 d-block mb-3 text-danger"></i>
                                        <div class="fw-bold mb-1">No Salary Slab Match Found</div>
                                        <div>The employee's CTC (₹{{ number_format($employee->current_salary, 2) }}) does not fall inside any Salary Structure slab configured for the <strong>{{ $employee->payGroup->name }}</strong> Pay Group.</div>
                                    </div>
                                @else
                                    <!-- Component Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Code / Component</th>
                                                    <th>Type</th>
                                                    <th>Rule Formula</th>
                                                    <th class="text-end">Monthly Value</th>
                                                    <th class="text-end">Yearly Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $totalEarningsMonthly = 0;
                                                    $totalDeductionsMonthly = 0;
                                                @endphp
                                                @foreach($computedComponents as $compId => $compData)
                                                    @php
                                                        $item = $compData['item'];
                                                        $amt = $compData['amount'];
                                                        $calcTypeLabel = match($item->calculation_type) {
                                                            'fixed' => 'Fixed Amount',
                                                            'percentage_of_ctc' => $item->value . '% of CTC',
                                                            'percentage_of_basic' => $item->value . '% of Basic',
                                                            'balancing' => 'Balancing / Remainder',
                                                            default => $item->calculation_type
                                                        };
                                                        
                                                        if ($item->component->type === 'earning') {
                                                            $totalEarningsMonthly += $amt / 12;
                                                        } else {
                                                            $totalDeductionsMonthly += $amt / 12;
                                                        }
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold text-dark">{{ $item->component->name }}</div>
                                                            <code class="fs-12">{{ $item->component->code }}</code>
                                                        </td>
                                                        <td>
                                                            @if($item->component->type === 'earning')
                                                                <span class="badge bg-soft-success text-success">Earning</span>
                                                            @else
                                                                <span class="badge bg-soft-danger text-danger">Deduction</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-muted fs-13">{{ $calcTypeLabel }}</td>
                                                        <td class="text-end fw-semibold">₹{{ number_format($amt / 12, 2) }}</td>
                                                        <td class="text-end fw-semibold text-primary">₹{{ number_format($amt, 2) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-light border-top-2">
                                                <tr>
                                                    <td colspan="3" class="fw-bold">Total Earnings:</td>
                                                    <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly, 2) }}</td>
                                                    <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly * 12, 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="fw-bold">Total Deductions:</td>
                                                    <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly, 2) }}</td>
                                                    <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly * 12, 2) }}</td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="3" class="fw-bold">Net Salary (In-Hand):</td>
                                                    <td class="text-end fw-extrabold text-primary">₹{{ number_format(max(0, $totalEarningsMonthly - $totalDeductionsMonthly), 2) }}</td>
                                                    <td class="text-end fw-extrabold text-primary">₹{{ number_format(max(0, $totalEarningsMonthly - $totalDeductionsMonthly) * 12, 2) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right: Summary & Adhoc components -->
                    <div class="col-lg-4 col-12">
                        <div class="card-custom mb-4">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-info text-primary"></i> Master Summary</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex flex-column gap-3">
                                    <div>
                                        <div class="info-label">Pay Group</div>
                                        <div class="info-value text-dark">{{ $employee->payGroup?->name ?? 'Not Assigned' }}</div>
                                    </div>
                                    <div>
                                        <div class="info-label">Annual Cost to Company (CTC)</div>
                                        <div class="info-value text-primary fs-18 fw-bold">₹{{ number_format($employee->current_salary, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-plus-circle text-primary"></i> Monthly Adhoc Components</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAdhocModal" @disabled(!$employee->pay_group_id)>
                                    Add
                                </button>
                            </div>
                            <div class="card-body p-0">
                                @if($adhocComponents->isEmpty())
                                    <div class="p-4 text-center text-muted fs-13">
                                        No ad-hoc components (bonuses, variable components) logged yet.
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Component</th>
                                                    <th>Month</th>
                                                    <th class="text-end">Amount</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($adhocComponents as $adhoc)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold">{{ $adhoc->component->name }}</div>
                                                            <span class="badge bg-soft-info text-info fs-10">{{ $adhoc->status }}</span>
                                                        </td>
                                                        <td><code>{{ $adhoc->payroll_month }}</code></td>
                                                        <td class="text-end fw-semibold">₹{{ number_format($adhoc->amount, 2) }}</td>
                                                        <td class="text-end">
                                                            <form action="{{ route('hrms.employees.adhoc-components.destroy', $adhoc->id) }}" method="POST" onsubmit="return confirm('Delete this adhoc component?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-link text-danger p-1 m-0"><i class="feather-trash-2"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. LEAVES TAB -->
            <div class="tab-pane fade" id="leaves-pane" role="tabpanel" aria-labelledby="leaves-tab">
                <div class="row">
                    <div class="col-md-4 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-info text-primary"></i> Assigned Leave Plan</h5>
                            </div>
                            <div class="card-body p-4">
                                @if($employee->leavePlan)
                                    <div class="mb-3">
                                        <h6 class="fw-bold mb-1">{{ $employee->leavePlan->name }}</h6>
                                        <p class="text-muted fs-13 mb-0">{{ $employee->leavePlan->description ?: 'No description provided.' }}</p>
                                    </div>
                                    <div class="border-top pt-3">
                                        <div class="info-label">Effective From</div>
                                        <div class="info-value">{{ $employee->leavePlan->effective_from ? $employee->leavePlan->effective_from->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                @else
                                    <div class="text-center py-3 text-muted fs-13">
                                        <i class="feather-alert-circle d-block fs-24 mb-2 text-warning"></i>
                                        No Leave Plan assigned to this employee.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-list text-primary"></i> Leave Allowances & Types</h5>
                            </div>
                            <div class="card-body p-0">
                                @if(!$employee->leavePlan || $employee->leavePlan->types->isEmpty())
                                    <div class="p-5 text-center text-muted">
                                        No Leave Types configured in the leave plan.
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Code / Type Name</th>
                                                    <th>Category</th>
                                                    <th class="text-end">Yearly Quota</th>
                                                    <th class="text-center">Rules</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($employee->leavePlan->types as $ltype)
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="d-inline-block rounded-circle" style="width: 12px; height: 12px; background-color: {{ $ltype->color ?: '#3b82f6' }};"></span>
                                                                <div>
                                                                    <div class="fw-bold text-dark">{{ $ltype->name }}</div>
                                                                    <code>{{ $ltype->code }}</code>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-soft-info text-info text-uppercase fs-11">{{ $ltype->type }}</span>
                                                        </td>
                                                        <td class="text-end fw-bold">{{ floatval($ltype->quota) }} Days</td>
                                                        <td class="text-center">
                                                            <button
                                                                type="button"
                                                                class="leave-rules-icon-btn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#leaveRulesModal{{ $ltype->id }}"
                                                                title="View leave rules"
                                                                aria-label="View rules for {{ $ltype->name }}"
                                                            >
                                                                <i class="feather-sliders"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. PENALIZATION TAB -->
            <div class="tab-pane fade" id="penalization-pane" role="tabpanel" aria-labelledby="penalization-tab">
                <div class="row g-4">
                    <!-- Left: Applied Penalties Log -->
                    <div class="col-lg-8 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-list text-primary"></i> Attendance Penalization History</h5>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addPenaltyModal" @disabled(!$attendancePenalty)>
                                    Log Instance
                                </button>
                            </div>
                            <div class="card-body p-0">
                                @if($penalties->isEmpty())
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-check-circle fs-32 d-block mb-3 text-success"></i>
                                        <div class="fw-bold mb-1">No Penalty Instances Logged</div>
                                        <div>This employee has no active penalty records.</div>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date Occurred</th>
                                                    <th>Rule Violation</th>
                                                    <th>Status</th>
                                                    <th>Month</th>
                                                    <th class="text-end">Deduction / Penalty</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($penalties as $penalty)
                                                    <tr>
                                                        <td class="fw-semibold">{{ $penalty->date ? $penalty->date->format('d M, Y') : 'N/A' }}</td>
                                                        <td>
                                                            <div class="text-dark fw-bold">{{ ucwords(str_replace('_', ' ', $penalty->rule_type)) }}</div>
                                                            <small class="text-muted d-block">{{ $penalty->remarks ?: 'No remarks' }}</small>
                                                        </td>
                                                        <td>
                                                            @if($penalty->status === 'applied')
                                                                <span class="badge bg-soft-danger text-danger">Applied</span>
                                                            @elseif($penalty->status === 'waived')
                                                                <span class="badge bg-soft-success text-success">Waived</span>
                                                            @else
                                                                <span class="badge bg-soft-warning text-warning">Pending</span>
                                                            @endif
                                                        </td>
                                                        <td><code>{{ $penalty->payroll_month }}</code></td>
                                                        <td class="text-end fw-bold text-danger">{{ floatval($penalty->penalty_amount) }} Days / Amt</td>
                                                        <td class="text-end">
                                                            <form action="{{ route('hrms.employees.penalties.destroy', $penalty->id) }}" method="POST" onsubmit="return confirm('Delete this penalty log?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-link text-danger p-1 m-0"><i class="feather-trash-2"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right: Policy Summary -->
                    <div class="col-lg-4 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-shield text-primary"></i> Company Penalization Policy</h5>
                            </div>
                            <div class="card-body p-4">
                                @if($attendancePenalties && $attendancePenalties->isNotEmpty())
                                    @foreach($attendancePenalties as $index => $policy)
                                        <div class="policy-info-pane {{ $index === 0 ? '' : 'd-none' }}" data-index="{{ $index }}" data-policy-type="{{ $policy->rule_type }}">
                                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="info-label">Active Rule Type</div>
                                                    <div class="info-value text-dark text-capitalize fw-bold" style="font-size: 15px;">
                                                        {{ str_replace('_', ' ', $policy->rule_type) }}
                                                    </div>
                                                </div>
                                                @if($attendancePenalties->count() > 1)
                                                    <ul class="erp-pagination mb-0" style="gap: 6px;">
                                                        <li class="page-item btn-prev-item-wrapper">
                                                            <button type="button" class="page-link btn-prev-policy-slide" style="width: 32px; height: 32px;" aria-label="Previous">
                                                                <i class="feather-chevron-left" style="font-weight: 600;"></i>
                                                            </button>
                                                        </li>
                                                        <li class="page-item btn-next-item-wrapper">
                                                            <button type="button" class="page-link btn-next-policy-slide" style="width: 32px; height: 32px;" aria-label="Next">
                                                                <i class="feather-chevron-right" style="font-weight: 600;"></i>
                                                            </button>
                                                        </li>
                                                    </ul>
                                                @endif
                                            </div>
                                            
                                            <div class="row g-3">
                                                @if($policy->rule_type === 'late_arrival')
                                                    <div class="col-6">
                                                        <div class="info-label">Grace Period</div>
                                                        <div class="info-value">{{ $policy->grace_period_minutes }} Mins</div>
                                                    </div>
                                                @elseif($policy->rule_type === 'under_hours')
                                                    <div class="col-6">
                                                        <div class="info-label">Daily Target</div>
                                                        <div class="info-value">{{ floatval($policy->grace_period_minutes / 60) }} Hours</div>
                                                    </div>
                                                @else
                                                    <div class="col-6">
                                                        <div class="info-label">Grace Period</div>
                                                        <div class="info-value">N/A</div>
                                                    </div>
                                                @endif
                                                <div class="col-6">
                                                    <div class="info-label">Threshold Count</div>
                                                    <div class="info-value">{{ $policy->threshold_count }} Marks</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Deduction Type</div>
                                                    <div class="info-value text-capitalize">{{ str_replace('_', ' ', $policy->penalty_action) }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Penalty Value</div>
                                                    <div class="info-value text-danger fw-bold">{{ floatval($policy->penalty_value) }} Deduct</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="text-center py-4 text-muted fs-13">
                                        <i class="feather-alert-circle d-block fs-24 mb-2 text-warning"></i>
                                        No company-wide Penalization Policy configured for <strong>{{ $employee->company?->company_name ?? 'this company' }}</strong>.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD ADHOC MODAL -->
    <div class="modal fade" id="addAdhocModal" tabindex="-1" aria-labelledby="addAdhocModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addAdhocModalLabel">Add Adhoc Salary Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.adhoc-components.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Adhoc Component" name="salary_component_id" select2-selector="default" :required="true">
                                    <option value="">Select Component</option>
                                    @foreach($availableAdhocComponents as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->name }} [{{ $ac->code }}]</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Amount (₹)" name="amount" inputType="number" step="0.01" placeholder="0.00" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Payroll Month" name="payroll_month" placeholder="YYYY-MM (e.g. 2026-07)" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks" rows="3" placeholder="Bonus details, adjustment notes..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Component</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD PENALTY MODAL -->
    <div class="modal fade" id="addPenaltyModal" tabindex="-1" aria-labelledby="addPenaltyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addPenaltyModalLabel">Log Attendance Penalty Instance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.penalties.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Rule Violated" name="rule_type" select2-selector="default" :required="true">
                                    <option value="">Select Rule Violation</option>
                                    <option value="no_attendance">No Attendance</option>
                                    <option value="late_arrival">Late Arrival</option>
                                    <option value="under_hours">Under Hours</option>
                                    <option value="missing_logs">Missing Logs</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Violation Date" name="date" inputType="date" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Deduction Value / Amount" name="penalty_amount" inputType="number" step="0.01" placeholder="e.g. 0.50 (for half-day)" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Payroll Month" name="payroll_month" placeholder="YYYY-MM (e.g. 2026-07)" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Remarks" name="remarks" rows="3" placeholder="Late arrival note, log timestamps..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Log Penalty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($employee->leavePlan && $employee->leavePlan->types->isNotEmpty())
        @foreach($employee->leavePlan->types as $ltype)
            @php($rulePoints = $formatLeaveRulePoints($ltype->rules ?? []))
            <div class="modal fade" id="leaveRulesModal{{ $ltype->id }}" tabindex="-1" aria-labelledby="leaveRulesModalLabel{{ $ltype->id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title fw-bold" id="leaveRulesModalLabel{{ $ltype->id }}">
                                    <i class="feather-sliders text-primary me-2"></i>{{ $ltype->name }} Rules
                                </h5>
                                <div class="text-muted fs-12 mt-1">
                                    {{ $ltype->code }} · {{ ucfirst($ltype->type) }} · {{ floatval($ltype->quota) }} days yearly quota
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body bg-light">
                            @if(empty($rulePoints))
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-check-circle d-block fs-32 mb-3 text-success"></i>
                                    <div class="fw-bold text-dark mb-1">Standard rules apply</div>
                                    <div>No custom leave rules are configured for this leave type.</div>
                                </div>
                            @else
                                <div class="row g-3">
                                    @foreach($rulePoints as $section)
                                        <div class="col-md-6 col-12">
                                            <div class="leave-rule-detail-section">
                                                <div class="leave-rule-detail-title">
                                                    <i class="{{ $section['icon'] }} text-primary"></i>
                                                    <span>{{ $section['title'] }}</span>
                                                </div>
                                                <ul class="leave-rule-points">
                                                    @foreach($section['points'] as $point)
                                                        <li class="leave-rule-point">{{ $point }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer bg-white">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @push('scripts')
        <script>
            $(document).ready(function() {
                // Move modals to body root to prevent Bootstrap backdrop overlay issues inside tabs
                $('#addAdhocModal').appendTo('body');
                $('#addPenaltyModal').appendTo('body');
                $('[id^="leaveRulesModal"]').appendTo('body');

                // Policy summary slider navigation
                function updatePolicySliderButtons() {
                    let panes = $('.policy-info-pane');
                    let activePane = $('.policy-info-pane:not(.d-none)');
                    let activeIndex = panes.index(activePane);

                    let prevBtn = $('.btn-prev-policy-slide');
                    let nextBtn = $('.btn-next-policy-slide');
                    let prevWrapper = $('.btn-prev-item-wrapper');
                    let nextWrapper = $('.btn-next-item-wrapper');

                    if (activeIndex <= 0) {
                        prevBtn.prop('disabled', true);
                        prevWrapper.addClass('disabled');
                    } else {
                        prevBtn.prop('disabled', false);
                        prevWrapper.removeClass('disabled');
                    }

                    if (activeIndex >= panes.length - 1) {
                        nextBtn.prop('disabled', true);
                        nextWrapper.addClass('disabled');
                    } else {
                        nextBtn.prop('disabled', false);
                        nextWrapper.removeClass('disabled');
                    }
                }

                // Initial calculation
                updatePolicySliderButtons();

                $(document).on('click', '.btn-next-policy-slide', function(e) {
                    e.preventDefault();
                    let activePane = $('.policy-info-pane:not(.d-none)');
                    let nextPane = activePane.next('.policy-info-pane');
                    if (nextPane.length) {
                        activePane.addClass('d-none');
                        nextPane.removeClass('d-none');
                        updatePolicySliderButtons();
                    }
                });

                $(document).on('click', '.btn-prev-policy-slide', function(e) {
                    e.preventDefault();
                    let activePane = $('.policy-info-pane:not(.d-none)');
                    let prevPane = activePane.prev('.policy-info-pane');
                    if (prevPane.length) {
                        activePane.addClass('d-none');
                        prevPane.removeClass('d-none');
                        updatePolicySliderButtons();
                    }
                });
            });
        </script>
    @endpush
@endsection
