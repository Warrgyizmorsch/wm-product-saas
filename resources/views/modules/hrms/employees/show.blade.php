@extends('layouts.duralux')

@section('title', 'EMPLOYEE PROFILE | SaaS ERP')
@section('page-title', 'Employee Profile')
@section('breadcrumb', 'HRMS / Employees / Profile')

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button href="{{ route('hrms.employees.index') }}" variant="light" icon="feather-arrow-left">
            Back to Registry
        </x-ui.button>
        <form action="{{ route('hrms.employees.destroy', $employee->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this employee?');" style="display: inline;">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" variant="danger" icon="feather-trash-2">
                Delete Profile
            </x-ui.button>
        </form>
    </div>
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
        /* Document Badge Styles */
        .badge-mandatory {
            background-color: rgba(239, 68, 68, 0.08) !important;
            color: #ef4444 !important;
            font-weight: 600;
        }
        .badge-optional {
            background-color: rgba(100, 116, 139, 0.08) !important;
            color: #64748b !important;
            font-weight: 500;
        }
        .badge-expiry {
            background-color: rgba(245, 158, 11, 0.08) !important;
            color: #f59e0b !important;
            font-weight: 500;
        }
        .badge-no-expiry {
            background-color: rgba(16, 185, 129, 0.08) !important;
            color: #10b981 !important;
            font-weight: 500;
        }

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
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
        }

        .tab-nav-custom::-webkit-scrollbar {
            display: none; /* Chrome/Safari */
        }

        .tab-nav-custom .nav-item {
            flex-shrink: 0;
        }

        .tab-nav-custom .nav-link {
            border: none !important;
            border-bottom: 3px solid transparent !important;
            background: transparent !important;
            color: #64748b !important;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 16px;
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

        .text-primary {
            color: var(--bs-primary) !important;
        }

        .bg-soft-primary {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
        }

        .btn-link {
            text-decoration: none !important;
            box-shadow: none !important;
        }

        /* Table custom file upload styling */
        .erp-custom-file-upload {
            display: block;
            width: 100%;
        }
        .erp-custom-file-upload .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #ced4da;
            border-radius: 6px;
            padding: 6px 12px;
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            width: 100%;
        }
        .erp-custom-file-upload .file-upload-label:hover {
            background-color: #f1f5f9;
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        /* Documents registry toolbar */
        .documents-toolbar {
            row-gap: 10px;
        }

        .documents-search {
            height: 38px;
            min-width: 230px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .documents-search:focus-within {
            background: #fff;
            border-color: rgba(var(--bs-primary-rgb), 0.45);
            box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.08);
        }

        .documents-search input::placeholder {
            color: #94a3b8;
        }

        .documents-toolbar .sort-toggle-custom,
        .documents-toolbar .filter-toggle-custom,
        .document-action-btn {
            height: 38px;
            border-radius: 10px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border: 1px solid #dbe3ec !important;
            background: #fff !important;
            color: #0f172a !important;
            box-shadow: none !important;
            padding-inline: 14px !important;
        }

        .documents-toolbar .sort-toggle-custom:hover,
        .documents-toolbar .filter-toggle-custom:hover,
        .document-action-btn:hover {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            background: rgba(var(--bs-primary-rgb), 0.06) !important;
        }

        .document-action-btn-primary {
            border-color: var(--bs-primary) !important;
            background: var(--bs-primary) !important;
            color: #fff !important;
        }

        .document-action-btn-primary:hover {
            background: color-mix(in srgb, var(--bs-primary) 88%, #000) !important;
            color: #fff !important;
        }

        .documents-action-group {
            flex-wrap: nowrap;
        }

        .document-filter-panel {
            width: auto;
            max-width: 100%;
        }

        .document-filter-label {
            font-size: 11px;
            font-weight: 700;
        }

        .document-filter-panel .select2-container {
            width: 100% !important;
        }

        .document-filter-panel .select2-container--bootstrap-5 .select2-selection {
            min-height: 36px;
            border: 0 !important;
            border-bottom: 1px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background: #fff !important;
            box-shadow: none !important;
            padding-left: 0 !important;
            font-size: 13px;
        }

        .document-filter-panel .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .document-filter-panel .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-bottom-color: #0f172a !important;
            box-shadow: none !important;
        }

        .document-filter-select-dropdown {
            border: 1px solid #dbe3ec !important;
            border-radius: 10px !important;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12) !important;
            overflow: hidden;
        }

        .document-filter-select-dropdown .select2-search--dropdown {
            padding: 8px !important;
        }

        .document-filter-select-dropdown .select2-search__field {
            border: 1px solid #cbd5e1 !important;
            border-radius: 5px !important;
            min-height: 38px;
            outline: none !important;
        }

        .document-filter-select-dropdown .select2-results__option {
            padding: 10px 14px !important;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .document-filter-select-dropdown .select2-results__option--selected {
            background: #2b2525 !important;
            color: #fff !important;
        }

        .document-filter-select-dropdown .select2-results__option--highlighted {
            background: #f1f5f9 !important;
            color: #0f172a !important;
        }

        .document-filter-select-dropdown .select2-results__option--selected.select2-results__option--highlighted {
            background: #2b2525 !important;
            color: #fff !important;
        }

        .asset-toolbar .documents-search {
            min-width: 220px;
        }

        .asset-toolbar .sort-toggle-custom,
        .asset-toolbar .filter-toggle-custom,
        .asset-action-btn {
            height: 38px;
            border-radius: 10px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border: 1px solid #dbe3ec !important;
            background: #fff !important;
            color: #0f172a !important;
            box-shadow: none !important;
            padding-inline: 14px !important;
        }

        .asset-toolbar .sort-toggle-custom:hover,
        .asset-toolbar .filter-toggle-custom:hover,
        .asset-action-btn:hover {
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            background: rgba(var(--bs-primary-rgb), 0.06) !important;
        }

        .asset-action-btn-primary {
            border-color: var(--bs-primary) !important;
            background: var(--bs-primary) !important;
            color: #fff !important;
        }

        .asset-action-btn-primary:hover {
            background: color-mix(in srgb, var(--bs-primary) 88%, #000) !important;
            color: #fff !important;
        }

        .document-filter-footer {
            gap: 8px;
        }

        .document-filter-btn {
            height: 36px;
            font-size: 11px !important;
            font-weight: 700 !important;
        }

        .documents-table th {
            font-size: 11px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: #0f172a;
            background: #f8fafc;
        }

        .documents-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .documents-table tbody tr:hover {
            background-color: #fbfdff;
        }

        /* File Card and actions */
        .file-card-container {
            border: 1px solid #e2e8f0 !important;
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 280px;
            transition: all 0.2s ease-in-out;
        }
        .file-card-container:hover {
            border-color: #cbd5e1 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .file-action-btn {
            width: 28px;
            height: 28px;
            min-width: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50% !important;
            border: 1px solid #e2e8f0 !important;
            background-color: #ffffff !important;
            color: #64748b !important;
            transition: all 0.2s ease;
        }
        .file-action-btn:hover {
            color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            background-color: color-mix(in srgb, var(--bs-primary) 5%, transparent) !important;
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
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="false">
                    <i class="feather-file-text"></i> Documents
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="false">
                    <i class="feather-clock"></i> Employment History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assets-tab" data-bs-toggle="tab" data-bs-target="#assets-pane" type="button" role="tab" aria-controls="assets-pane" aria-selected="false">
                    <i class="feather-package"></i> Assigned Assets
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
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">Present Address</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->present_address ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">Permanent Address</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->permanent_address ?: 'N/A' }}</div>
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
                                <x-ui.button 
                                    type="button" 
                                    variant="soft-primary" 
                                    size="sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#addAdhocModal" 
                                    :disabled="!$employee->pay_group_id"
                                >
                                    ADD
                                </x-ui.button>
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
                                                     <th class="text-end">Amount</th>
                                                     <th class="text-end">Action</th>
                                                 </tr>
                                             </thead>
                                             <tbody>
                                                 @foreach($adhocComponents as $adhoc)
                                                     <tr>
                                                         <td>
                                                             <div class="fw-bold">{{ $adhoc->component->name }}</div>
                                                             <div class="d-flex align-items-center gap-2 mt-1">
                                                                 <span class="badge bg-soft-info text-info fs-10">{{ $adhoc->status }}</span>
                                                                 <span class="text-muted fs-11"><i class="feather-calendar me-1"></i>{{ $adhoc->payroll_month }}</span>
                                                             </div>
                                                         </td>
                                                         <td class="text-end fw-semibold">₹{{ number_format($adhoc->amount, 2) }}</td>
                                                         <td class="text-end">
                                                             <form action="{{ route('hrms.employees.adhoc-components.destroy', $adhoc->id) }}" method="POST" onsubmit="return confirm('Delete this adhoc component?');">
                                                                 @csrf
                                                                 @method('DELETE')
                                                                 <button type="submit" class="btn btn-link text-danger p-1 m-0" style="text-decoration: none !important; box-shadow: none !important;"><i class="feather-trash-2"></i></button>
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
                                                            @if($penalty->remarks)
                                                                @if(str_contains($penalty->remarks, ' ('))
                                                                    @php
                                                                        [$first, $second] = explode(' (', $penalty->remarks, 2);
                                                                    @endphp
                                                                    <small class="text-muted d-block">{{ $first }}</small>
                                                                    <small class="text-secondary fs-11 d-block">({{ $second }}</small>
                                                                @else
                                                                    <small class="text-muted d-block">{{ $penalty->remarks }}</small>
                                                                @endif
                                                            @else
                                                                <small class="text-muted d-block">No remarks</small>
                                                            @endif
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
                                                                <button type="submit" class="btn btn-link text-danger p-1 m-0" style="text-decoration: none !important; box-shadow: none !important;"><i class="feather-trash-2"></i></button>
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

            <!-- 5. DOCUMENTS TAB -->
            <div class="tab-pane fade" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab">
                <div class="row">
                    <div class="col-12">
                        <div class="card-custom">
                            <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                <div>
                                    <h5 class="card-custom-title"><i class="feather-file-text text-primary"></i> Employee Documents Registry</h5>
                                    <small class="text-muted d-block mt-1">Manage, upload, or request official documents for this employee.</small>
                                </div>
                                <div class="documents-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                                    <div class="documents-search d-flex align-items-center px-3 py-1">
                                        <i class="feather-search text-muted me-2 fs-14"></i>
                                        <input 
                                            type="text" 
                                            id="documentSearchInput" 
                                            class="form-control border-0 bg-transparent p-0 fs-13" 
                                            placeholder="Search documents..." 
                                            autocomplete="off"
                                            style="box-shadow: none; height: 32px;"
                                        >
                                    </div>

                                    <x-ui.sort-dropdown label="SORT">
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="title_asc">
                                            <span>Document Title (A-Z)</span>
                                            <i class="feather-check ms-3"></i>
                                        </a>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="title_desc">
                                            <span>Document Title (Z-A)</span>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_asc">
                                            <span>Expiry Date (Soonest)</span>
                                        </a>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_desc">
                                            <span>Expiry Date (Latest)</span>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <x-ui.filter label="FILTER">
                                        <div class="document-filter-panel">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                                        <form id="documentFilterForm" onsubmit="return false;">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Document Status</label>
                                                <x-ui.odoo-form-ui type="select" name="status">
                                                    <option value="">All Statuses</option>
                                                    <option value="uploaded">Uploaded</option>
                                                    <option value="requested">Pending Upload</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Expiry Requirement</label>
                                                <x-ui.odoo-form-ui type="select" name="has_expiry">
                                                    <option value="">All Documents</option>
                                                    <option value="1">Has Expiry</option>
                                                    <option value="0">No Expiry</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="dropdown-divider my-3"></div>
                                            <div class="document-filter-footer d-flex">
                                                <x-ui.button type="button" id="btnDocumentFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">Apply Filters</x-ui.button>
                                                <x-ui.button type="button" id="btnDocumentFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">Reset</x-ui.button>
                                            </div>
                                        </form>
                                        </div>
                                    </x-ui.filter>

                                    <div class="documents-action-group d-flex align-items-center gap-2">
                                        @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                            <x-ui.button variant="light" size="sm" class="document-action-btn" data-bs-toggle="modal" data-bs-target="#requestDocumentModal" icon="feather-git-pull-request">
                                                Request Document
                                            </x-ui.button>
                                        @endif
                                        <x-ui.button variant="primary" size="sm" class="document-action-btn document-action-btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal" icon="feather-upload-cloud">
                                            Upload Document
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                @if(session('error'))
                                    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
                                @endif
                                <div class="table-responsive border rounded bg-white">
                                    <table class="table table-bordered table-hover mb-0 align-middle text-center documents-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start" style="width: 250px;">Document Title</th>
                                                <th>Source / Requested By</th>
                                                <th>Expiry Date</th>
                                                <th>File</th>
                                                <th>Status</th>
                                                <th style="width: 280px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($employee->documents as $doc)
                                                @php
                                                    $documentSearchText = trim(implode(' ', array_filter([
                                                        $doc->name,
                                                        $doc->description,
                                                        $doc->requestedBy?->name,
                                                        $doc->file_name,
                                                        $doc->status,
                                                        $doc->has_expiry ? 'has expiry' : 'no expiry',
                                                    ])));
                                                @endphp
                                                <tr class="document-row"
                                                    data-title="{{ \Illuminate\Support\Str::lower($doc->name) }}"
                                                    data-search="{{ \Illuminate\Support\Str::lower($documentSearchText) }}"
                                                    data-status="{{ $doc->status }}"
                                                    data-has-expiry="{{ $doc->has_expiry ? '1' : '0' }}"
                                                    data-expiry="{{ $doc->expiry_date ? $doc->expiry_date->timestamp : '' }}">
                                                    <td class="text-start font-semibold">
                                                        <div class="text-dark fw-bold fs-13">{{ $doc->name }}</div>
                                                        @if($doc->description)
                                                            <div class="text-muted fs-10" style="font-size: 10px;">{{ $doc->description }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($doc->requestedBy)
                                                            <span class="text-secondary fw-medium fs-12">Requested by {{ $doc->requestedBy->name }}</span>
                                                        @else
                                                            <span class="text-muted fs-11">Direct Upload</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($doc->has_expiry)
                                                            @if($doc->expiry_date)
                                                                @php
                                                                    $isExpired = $doc->expiry_date->isPast();
                                                                    $isNearExpiry = !$isExpired && $doc->expiry_date->diffInDays(now()) <= 30; // default 30 days alert
                                                                @endphp
                                                                @if($isExpired)
                                                                    <span class="badge badge-mandatory"><i class="feather-alert-circle me-1"></i>Expired ({{ $doc->expiry_date->format('d M, Y') }})</span>
                                                                @elseif($isNearExpiry)
                                                                    <span class="badge badge-expiry"><i class="feather-clock me-1"></i>Near Expiry ({{ $doc->expiry_date->format('d M, Y') }})</span>
                                                                @else
                                                                    <span class="text-dark fw-semibold">{{ $doc->expiry_date->format('d M, Y') }}</span>
                                                                @endif
                                                            @else
                                                                <span class="text-muted fs-11">Required on Upload</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted fs-11">No Expiry</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-start">
                                                         @if($doc->file_path)
                                                             <div class="file-card-container">
                                                                 <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                                     <div class="d-flex align-items-center justify-content-center bg-soft-primary rounded text-primary" style="width: 32px; height: 32px; min-width: 32px;">
                                                                         <i class="feather-file fs-16"></i>
                                                                     </div>
                                                                     <div class="overflow-hidden">
                                                                         <div class="text-dark fw-bold fs-12 text-truncate" style="max-width: 130px;" title="{{ $doc->file_name }}">
                                                                             {{ $doc->file_name }}
                                                                         </div>
                                                                         <div class="text-muted fs-10">{{ number_format($doc->file_size / 1024, 1) }} KB</div>
                                                                     </div>
                                                                 </div>
                                                                 <div class="hstack gap-1">
                                                                     <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="file-action-btn" title="View Document" data-bs-toggle="tooltip">
                                                                         <i class="feather-eye fs-12"></i>
                                                                     </a>
                                                                     <a href="{{ asset('storage/' . $doc->file_path) }}" download="{{ $doc->file_name }}" class="file-action-btn" title="Download Document" data-bs-toggle="tooltip">
                                                                         <i class="feather-download fs-12"></i>
                                                                     </a>
                                                                 </div>
                                                             </div>
                                                         @else
                                                             <span class="text-muted fs-11 d-block text-center">No File Uploaded</span>
                                                         @endif
                                                    </td>
                                                    <td>
                                                        @if($doc->status === 'requested')
                                                            <span class="badge bg-soft-warning text-warning">Pending Upload</span>
                                                        @elseif($doc->status === 'uploaded')
                                                            <span class="badge bg-soft-success text-success">Uploaded</span>
                                                        @else
                                                            <span class="badge bg-soft-secondary text-secondary">{{ ucfirst($doc->status) }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($doc->status === 'requested')
                                                            <!-- File Upload Form for Pending Request -->
                                                            <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                                                                @csrf
                                                                <input type="hidden" name="document_id" value="{{ $doc->id }}">
                                                                <div class="flex-grow-1 text-start">
                                                                    <div class="erp-custom-file-upload">
                                                                        <label class="file-upload-label py-1.5 px-3" style="font-size: 11px; cursor: pointer; border-style: dashed; border-width: 1px;" for="table_file_{{ $doc->id }}">
                                                                            <i class="feather-upload-cloud me-1 text-primary fs-12"></i>
                                                                            <span class="file-text text-muted" id="file_text_{{ $doc->id }}">Choose File</span>
                                                                            <input type="file" name="file" id="table_file_{{ $doc->id }}" class="d-none" required onchange="document.getElementById('file_text_{{ $doc->id }}').innerText = this.files[0]?.name || 'Choose File'">
                                                                        </label>
                                                                    </div>
                                                                    @if($doc->has_expiry)
                                                                        <div class="mt-1 d-flex align-items-center gap-1">
                                                                            <span class="text-secondary fs-9 text-uppercase" style="font-size: 8px;">Expiry:</span>
                                                                            <input type="date" name="expiry_date" class="form-control form-control-sm py-0 px-1" required style="font-size: 10px; height: 20px; width: 120px;">
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-primary py-1 px-2 d-flex align-items-center justify-content-center" style="border-radius: 6px; font-size: 11px; height: 28px;">
                                                                    <i class="feather-upload-cloud me-1"></i> Upload
                                                                </button>
                                                            </form>
                                                        @else
                                                            <div class="d-flex justify-content-center gap-2">
                                                                @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                                    <form action="{{ route('hrms.employees.documents.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this document record?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 py-1 px-3" style="border-radius: 6px; font-size: 11px;">
                                                                            <i class="feather-trash-2"></i> Remove Record
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @if($employee->documents->isEmpty())
                                                <tr>
                                                    <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                        <i class="feather-file fs-24 d-block mb-2 text-secondary"></i>
                                                        No documents uploaded or requested for this employee profile yet.
                                                    </td>
                                                </tr>
                                            @else
                                                <tr id="documentNoResultsRow" class="d-none">
                                                    <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                        <i class="feather-search fs-24 d-block mb-2 text-secondary"></i>
                                                        No documents match your search or filters.
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. EMPLOYMENT HISTORY TAB -->
            <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                <div class="card-custom">
                    <div class="card-custom-header">
                        <div>
                            <h5 class="card-custom-title"><i class="feather-clock text-primary"></i> Previous Employment History</h5>
                            <small class="text-muted d-block mt-1">Timeline of previous roles and experiences outside this company.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary py-2 px-3 d-flex align-items-center gap-1" style="border-radius: 6px; font-size: 12px;" data-bs-toggle="modal" data-bs-target="#addHistoryModal">
                            <i class="feather-plus"></i> Add Experience
                        </button>
                    </div>
                    <div class="card-body p-4">
                        @if($employee->employmentHistories->isEmpty())
                            <div class="p-5 text-center text-muted">
                                <i class="feather-activity fs-32 d-block mb-3 text-secondary"></i>
                                <div class="fw-bold mb-1">No Previous Employment Records</div>
                                <div>Click "Add Experience" to document work history.</div>
                            </div>
                        @else
                            <div class="timeline-container px-3">
                                @foreach($employee->employmentHistories as $history)
                                    <div class="timeline-item position-relative pb-4" style="padding-left: 30px; border-left: 2px solid #e2e8f0;">
                                        <div class="timeline-badge position-absolute bg-primary rounded-circle" style="left: -7px; top: 0; width: 12px; height: 12px; border: 2px solid #fff; box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.2);"></div>
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1">{{ $history->designation }}</h6>
                                                <div class="fw-semibold text-secondary mb-2" style="font-size: 13px;">{{ $history->company_name }}</div>
                                                <span class="badge bg-soft-primary text-primary fs-11 px-2 py-1 rounded">
                                                    {{ $history->start_date->format('M Y') }} – {{ $history->end_date ? $history->end_date->format('M Y') : 'Present' }}
                                                </span>
                                                @if($history->job_description)
                                                    <p class="text-muted fs-13 mt-3 mb-0 text-wrap" style="max-width: 100%; white-space: pre-line;">{{ $history->job_description }}</p>
                                                @endif
                                            </div>
                                            @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                <form action="{{ route('hrms.employees.history.destroy', [$employee->id, $history->id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 py-1 px-3" style="border-radius: 6px; font-size: 11px;">
                                                        <i class="feather-trash-2"></i> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 7. ASSIGNED ASSETS TAB -->
            <div class="tab-pane fade" id="assets-pane" role="tabpanel" aria-labelledby="assets-tab">
                @php
                    $categories = \App\Domains\HRMS\Models\AssetCategory::query()->orderBy('name')->get();
                    $assignedAssetCategories = $employee->assets->pluck('category.name')->filter()->unique()->sort()->values();
                    $requestAssetCategories = $employee->assetRequests->pluck('category.name')->filter()->unique()->sort()->values();
                    $requestAssetStatuses = $employee->assetRequests->pluck('status')->filter()->unique()->sort()->values();
                @endphp
                <div class="card-custom">
                    <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h5 class="card-custom-title"><i class="feather-package text-primary"></i> Company Assets</h5>
                            <small class="text-muted d-block mt-1">Laptops, devices, and other assets currently assigned to this employee.</small>
                        </div>
                        <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                            <div class="documents-search d-flex align-items-center px-3 py-1">
                                <i class="feather-search text-muted me-2 fs-14"></i>
                                <input type="text" id="assignedAssetSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search assigned assets..." autocomplete="off" style="box-shadow: none; height: 32px;">
                            </div>
                            <x-ui.sort-dropdown label="SORT">
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="name_asc">
                                    <span>Asset Name (A-Z)</span>
                                    <i class="feather-check ms-3"></i>
                                </a>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="name_desc">
                                    <span>Asset Name (Z-A)</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_desc">
                                    <span>Assigned Date (Latest)</span>
                                </a>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_asc">
                                    <span>Assigned Date (Oldest)</span>
                                </a>
                            </x-ui.sort-dropdown>
                            <x-ui.filter label="FILTER">
                                <div class="document-filter-panel">
                                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                                    <form id="assignedAssetFilterForm" onsubmit="return false;">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Asset Category</label>
                                            <x-ui.odoo-form-ui type="select" name="category">
                                                <option value="">All Categories</option>
                                                @foreach($assignedAssetCategories as $categoryName)
                                                    <option value="{{ \Illuminate\Support\Str::lower($categoryName) }}">{{ $categoryName }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Serial Number</label>
                                            <x-ui.odoo-form-ui type="select" name="serial">
                                                <option value="">All Assets</option>
                                                <option value="1">Has Serial Number</option>
                                                <option value="0">No Serial Number</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="dropdown-divider my-3"></div>
                                        <div class="document-filter-footer d-flex">
                                            <x-ui.button type="button" id="btnAssignedAssetFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">Apply Filters</x-ui.button>
                                            <x-ui.button type="button" id="btnAssignedAssetFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">Reset</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            </x-ui.filter>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-hover align-middle mb-0 text-center assigned-assets-table">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start" style="width: 150px; padding-left: 20px;">Asset Tag / Code</th>
                                        <th class="text-start">Asset Name</th>
                                        <th>Category</th>
                                        <th>Serial Number</th>
                                        <th>Assigned At</th>
                                        <th class="text-end" style="width: 180px; padding-right: 20px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($employee->assets as $asset)
                                        @php
                                            $assetSearchText = trim(implode(' ', array_filter([
                                                $asset->asset_code,
                                                $asset->name,
                                                $asset->brand,
                                                $asset->model_number,
                                                $asset->category?->name,
                                                $asset->serial_number,
                                            ])));
                                        @endphp
                                        <tr class="assigned-asset-row"
                                            data-name="{{ \Illuminate\Support\Str::lower($asset->name) }}"
                                            data-search="{{ \Illuminate\Support\Str::lower($assetSearchText) }}"
                                            data-category="{{ \Illuminate\Support\Str::lower($asset->category?->name) }}"
                                            data-has-serial="{{ $asset->serial_number ? '1' : '0' }}"
                                            data-assigned="{{ $asset->allocated_at ? $asset->allocated_at->timestamp : '' }}">
                                            <td class="text-start" style="padding-left: 20px;"><code class="fw-bold fs-13">{{ $asset->asset_code }}</code></td>
                                            <td class="text-start">
                                                <div class="fw-bold text-dark fs-13">{{ $asset->name }}</div>
                                                <small class="text-muted fs-11">{{ $asset->brand }} {{ $asset->model_number }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary">{{ $asset->category->name }}</span>
                                            </td>
                                            <td><span class="text-dark fw-semibold fs-13">{{ $asset->serial_number ?: 'N/A' }}</span></td>
                                            <td><span class="text-secondary fs-13">{{ $asset->allocated_at ? $asset->allocated_at->format('d M, Y') : 'N/A' }}</span></td>
                                            <td class="text-end" style="padding-right: 20px;">
                                                @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                    <button type="button" class="btn btn-sm btn-light border text-uppercase fw-bold px-3 d-inline-flex align-items-center justify-content-center" style="border-color: #cbd5e1; background-color: #ffffff; color: #475569; font-size: 11px; letter-spacing: 0.5px; height: 32px; border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-asset-id="{{ $asset->id }}" data-asset-name="{{ $asset->name }} ({{ $asset->asset_code }})">
                                                         Return Asset
                                                    </button>
                                                @else
                                                    <span class="text-muted fs-12">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                <i class="feather-package fs-24 d-block mb-2 text-secondary"></i>
                                                No company assets are currently assigned to this employee.
                                            </td>
                                        </tr>
                                    @endforelse
                                    @if($employee->assets->isNotEmpty())
                                        <tr id="assignedAssetNoResultsRow" class="d-none">
                                            <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                <i class="feather-search fs-24 d-block mb-2 text-secondary"></i>
                                                No assigned assets match your search or filters.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab card 2: Asset Requests History -->
                    <div class="card border rounded bg-white shadow-sm mt-4">
                        <div class="card-header border-bottom d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 py-3 px-4 bg-white">
                            <div>
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Asset Requests Log</h5>
                                <small class="text-muted fs-11">Trace requests submitted by this employee for hardware or credentials.</small>
                            </div>
                            <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                                <div class="documents-search d-flex align-items-center px-3 py-1">
                                    <i class="feather-search text-muted me-2 fs-14"></i>
                                    <input type="text" id="assetRequestSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search requests..." autocomplete="off" style="box-shadow: none; height: 32px;">
                                </div>
                                <x-ui.sort-dropdown label="SORT">
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="date_desc">
                                        <span>Request Date (Latest)</span>
                                        <i class="feather-check ms-3"></i>
                                    </a>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="date_asc">
                                        <span>Request Date (Oldest)</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="category_asc">
                                        <span>Category (A-Z)</span>
                                    </a>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="status_asc">
                                        <span>Status (A-Z)</span>
                                    </a>
                                </x-ui.sort-dropdown>
                                <x-ui.filter label="FILTER">
                                    <div class="document-filter-panel">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                                        <form id="assetRequestFilterForm" onsubmit="return false;">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Requested Category</label>
                                                <x-ui.odoo-form-ui type="select" name="category">
                                                    <option value="">All Categories</option>
                                                    @foreach($requestAssetCategories as $categoryName)
                                                        <option value="{{ \Illuminate\Support\Str::lower($categoryName) }}">{{ $categoryName }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Request Status</label>
                                                <x-ui.odoo-form-ui type="select" name="status">
                                                    <option value="">All Statuses</option>
                                                    @foreach($requestAssetStatuses as $statusName)
                                                        <option value="{{ $statusName }}">{{ ucfirst($statusName) }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="dropdown-divider my-3"></div>
                                            <div class="document-filter-footer d-flex">
                                                <x-ui.button type="button" id="btnAssetRequestFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">Apply Filters</x-ui.button>
                                                <x-ui.button type="button" id="btnAssetRequestFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">Reset</x-ui.button>
                                            </div>
                                        </form>
                                    </div>
                                </x-ui.filter>
                                <button type="button" class="btn btn-sm asset-action-btn asset-action-btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#requestAssetModal">
                                    <i class="feather-plus"></i> Request Asset
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 text-center asset-requests-table">
                                    <thead class="table-light text-uppercase fs-10" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start" style="padding-left: 20px;">Requested Category</th>
                                            <th>Request Date</th>
                                            <th class="text-start">Reason</th>
                                            <th>Status</th>
                                            <th class="text-start" style="padding-right: 20px;">Admin Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($employee->assetRequests as $req)
                                            @php
                                                $requestSearchText = trim(implode(' ', array_filter([
                                                    $req->category?->name,
                                                    $req->reason,
                                                    $req->status,
                                                    $req->admin_notes,
                                                ])));
                                            @endphp
                                            <tr class="asset-request-row"
                                                data-category="{{ \Illuminate\Support\Str::lower($req->category?->name) }}"
                                                data-status="{{ $req->status }}"
                                                data-search="{{ \Illuminate\Support\Str::lower($requestSearchText) }}"
                                                data-date="{{ $req->request_date ? $req->request_date->timestamp : '' }}">
                                                <td class="text-start" style="padding-left: 20px;">
                                                    <span class="badge bg-soft-primary text-primary">{{ $req->category->name }}</span>
                                                </td>
                                                <td><span class="text-secondary fs-12">{{ $req->request_date ? $req->request_date->format('d M, Y') : '-' }}</span></td>
                                                <td class="text-start text-muted fs-12" style="max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="{{ $req->reason }}">{{ $req->reason }}</td>
                                                <td>
                                                    @if($req->status === 'pending')
                                                        <span class="badge bg-soft-warning text-warning px-2 py-1 text-capitalize fs-11">{{ $req->status }}</span>
                                                    @elseif($req->status === 'allocated')
                                                        <span class="badge bg-soft-success text-success px-2 py-1 text-capitalize fs-11">{{ $req->status }}</span>
                                                    @elseif($req->status === 'rejected')
                                                        <span class="badge bg-soft-danger text-danger px-2 py-1 text-capitalize fs-11">{{ $req->status }}</span>
                                                    @else
                                                        <span class="badge bg-light text-secondary px-2 py-1 text-capitalize fs-11">{{ $req->status }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-start text-muted fs-11" style="padding-right: 20px;">{{ $req->admin_notes ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted fs-11">
                                                    No asset requests have been submitted by this employee.
                                                </td>
                                            </tr>
                                        @endforelse
                                        @if($employee->assetRequests->isNotEmpty())
                                            <tr id="assetRequestNoResultsRow" class="d-none">
                                                <td colspan="5" class="text-center py-4 text-muted fs-11">
                                                    <i class="feather-search fs-20 d-block mb-2 text-secondary"></i>
                                                    No asset requests match your search or filters.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HR REQUEST DOCUMENT MODAL -->
        <div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark" id="requestDocumentModalLabel">
                            <i class="feather-git-pull-request me-2 text-primary" style="font-size: 16px;"></i>Request Document from Employee
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.documents.request', $employee->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="Document Name" name="name" :required="true" placeholder="e.g. Previous Experience Certificate, 10th Marksheet" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="Instructions" name="description" placeholder="Explain what details or formatting are required..." />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="radio" label="Requires Expiry" name="has_expiry" :required="true">
                                        <div class="form-check">
                                            <input type="radio" id="has_expiry_yes" name="has_expiry" value="1" class="form-check-input">
                                            <label class="form-check-label fs-13" for="has_expiry_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" id="has_expiry_no" name="has_expiry" value="0" class="form-check-input" checked>
                                            <label class="form-check-label fs-13" for="has_expiry_no">No</label>
                                        </div>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Send Request</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- DIRECT UPLOAD DOCUMENT MODAL -->
        <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark" id="uploadDocumentModalLabel">
                            <i class="feather-upload-cloud me-2 text-primary" style="font-size: 16px;"></i>Upload New Document
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="Document Title" name="name" :required="true" placeholder="e.g. Aadhar Card, Offer Letter" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="file" label="Select File" name="file" :required="true" placeholder="Click to upload PDF, JPG, PNG (Max 10MB)..." />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="Expiry Date" name="expiry_date" inputType="date" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Upload File</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ADD EMPLOYMENT HISTORY MODAL -->
        <div class="modal fade" id="addHistoryModal" tabindex="-1" aria-labelledby="addHistoryModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark" id="addHistoryModalLabel">
                            <i class="feather-clock me-2 text-primary" style="font-size: 16px;"></i>Add Previous Work Experience
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.history.store', $employee->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="Company Name" name="company_name" placeholder="e.g. Acme Corp" :required="true" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="Designation" name="designation" placeholder="e.g. Senior Software Engineer" :required="true" />
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="Start Date" name="start_date" inputType="date" :required="true" />
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="End Date" name="end_date" inputType="date" placeholder="Leave empty if present" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="Job Description" name="job_description" rows="3" placeholder="Explain your key responsibilities, achievements, and tech stack..." />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Save Experience</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                        </div>
                    </form>
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

    <!-- RETURN ASSET MODAL FOR PROFILE TAB -->
    <div class="modal fade" id="returnAssetModal" tabindex="-1" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="returnAssetModalLabel">
                        <i class="feather-corner-up-left me-2 text-primary"></i>Return Asset to Inventory
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="returnAssetForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">Asset To Return</label>
                                <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="Return Date" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Return Condition" name="return_condition" :required="true" select2-selector="default">
                                    <option value="good">Good</option>
                                    <option value="new">New</option>
                                    <option value="fair">Fair</option>
                                    <option value="damaged">Damaged (Needs Maintenance)</option>
                                    <option value="scrapped">Scrapped</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Return Notes" name="return_notes" placeholder="Condition details, damage details, return notes..." />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Process Return</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Cancel</button>
                    </div>
                </form>
            </div>
    <!-- REQUEST ASSET MODAL FOR PROFILE TAB -->
    <div class="modal fade" id="requestAssetModal" tabindex="-1" aria-labelledby="requestAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="requestAssetModalLabel">
                        <i class="feather-plus me-2 text-primary"></i>Request Company Asset
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.requests.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">Employee Requesting</label>
                                <input type="text" class="form-control bg-light" value="{{ $employee->display_name }} ({{ $employee->employee_id }})" readonly>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="Asset Category" name="asset_category_id" :required="true" select2-selector="default">
                                    <option value="">Select Category</option>
                                    @foreach($categories->where('company_id', $employee->company_id) as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Reason for Request" name="reason" placeholder="Please specify why you need this asset..." :required="true" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Submit Request</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                // Move modals to body root to prevent Bootstrap backdrop overlay issues inside tabs
                $('#addAdhocModal').appendTo('body');
                $('#addPenaltyModal').appendTo('body');
                $('[id^="leaveRulesModal"]').appendTo('body');
                $('#requestDocumentModal').appendTo('body');
                $('#uploadDocumentModal').appendTo('body');
                $('#addHistoryModal').appendTo('body');
                $('#returnAssetModal').appendTo('body');
                $('#requestAssetModal').appendTo('body');

                // Handle return modal details binding
                $('#returnAssetModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    var assetId = button.data('asset-id');
                    var assetName = button.data('asset-name');

                    var modal = $(this);
                    modal.find('form').attr('action', '/hrms/assets/' + assetId + '/return');
                    modal.find('#return_asset_name_display').val(assetName);
                });

                // Keep active tab on refresh / redirect
                const urlParams = new URLSearchParams(window.location.search);
                const activeTab = urlParams.get('tab');
                if (activeTab) {
                    const tabEl = document.querySelector(`#${activeTab}-tab`);
                    if (tabEl) {
                        const tab = new bootstrap.Tab(tabEl);
                        tab.show();
                    }
                }

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

                const $documentRows = $('.document-row');
                const $documentTbody = $('.documents-table tbody');
                const $documentNoResultsRow = $('#documentNoResultsRow');
                let documentSortMode = 'title_asc';
                let appliedDocumentFilters = {
                    status: '',
                    hasExpiry: '',
                };

                function normalizeText(value) {
                    return String(value || '').toLowerCase().trim();
                }

                function getDocumentFilters() {
                    return {
                        search: normalizeText($('#documentSearchInput').val()),
                        status: appliedDocumentFilters.status,
                        hasExpiry: appliedDocumentFilters.hasExpiry,
                    };
                }

                function setDocumentFilterChoice(groupName, value) {
                    $(`#documentFilterForm [name="${groupName}"]`).val(value).trigger('change');
                }

                function initDocumentFilterSelects() {
                    $('.document-filter-select, .asset-filter-select').each(function() {
                        const $select = $(this);

                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }

                        $select.select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            dropdownParent: $select.closest('.dropdown-menu'),
                            dropdownCssClass: 'document-filter-select-dropdown',
                            placeholder: $select.data('placeholder') || '',
                        });
                    });
                }

                function compareDocumentRows(firstRow, secondRow) {
                    const $first = $(firstRow);
                    const $second = $(secondRow);
                    const firstTitle = $first.data('title') || '';
                    const secondTitle = $second.data('title') || '';
                    const firstExpiry = parseInt($first.data('expiry'), 10);
                    const secondExpiry = parseInt($second.data('expiry'), 10);
                    const firstHasExpiryDate = !Number.isNaN(firstExpiry);
                    const secondHasExpiryDate = !Number.isNaN(secondExpiry);
                    const firstExpiryValue = firstHasExpiryDate ? firstExpiry : Number.MAX_SAFE_INTEGER;
                    const secondExpiryValue = secondHasExpiryDate ? secondExpiry : Number.MAX_SAFE_INTEGER;

                    if (documentSortMode === 'title_desc') {
                        return secondTitle.localeCompare(firstTitle);
                    }

                    if (documentSortMode === 'expiry_asc') {
                        return firstExpiryValue - secondExpiryValue || firstTitle.localeCompare(secondTitle);
                    }

                    if (documentSortMode === 'expiry_desc') {
                        if (!firstHasExpiryDate && !secondHasExpiryDate) {
                            return firstTitle.localeCompare(secondTitle);
                        }

                        if (!firstHasExpiryDate) {
                            return 1;
                        }

                        if (!secondHasExpiryDate) {
                            return -1;
                        }

                        return secondExpiryValue - firstExpiryValue || firstTitle.localeCompare(secondTitle);
                    }

                    return firstTitle.localeCompare(secondTitle);
                }

                function refreshDocumentRows() {
                    const filters = getDocumentFilters();
                    let visibleCount = 0;
                    const sortedRows = $documentRows.toArray().sort(compareDocumentRows);

                    $.each(sortedRows, function(_, row) {
                        const $row = $(row);
                        const matchesSearch = !filters.search || normalizeText($row.data('search')).includes(filters.search);
                        const matchesStatus = !filters.status || $row.data('status') === filters.status;
                        const matchesExpiry = filters.hasExpiry === '' || String($row.data('has-expiry')) === filters.hasExpiry;
                        const isVisible = matchesSearch && matchesStatus && matchesExpiry;

                        $row.toggleClass('d-none', !isVisible);
                        if (isVisible) {
                            visibleCount++;
                        }

                        $documentTbody.append(row);
                    });

                    if ($documentNoResultsRow.length) {
                        $documentTbody.append($documentNoResultsRow);
                        $documentNoResultsRow.toggleClass('d-none', visibleCount > 0);
                    }
                }

                const $assignedAssetRows = $('.assigned-asset-row');
                const $assignedAssetTbody = $('.assigned-assets-table tbody');
                const $assignedAssetNoResultsRow = $('#assignedAssetNoResultsRow');
                let assignedAssetSortMode = 'name_asc';
                let appliedAssignedAssetFilters = {
                    category: '',
                    serial: '',
                };

                function getAssignedAssetFilters() {
                    return {
                        search: normalizeText($('#assignedAssetSearchInput').val()),
                        category: appliedAssignedAssetFilters.category,
                        serial: appliedAssignedAssetFilters.serial,
                    };
                }

                function compareAssignedAssetRows(firstRow, secondRow) {
                    const $first = $(firstRow);
                    const $second = $(secondRow);
                    const firstName = $first.data('name') || '';
                    const secondName = $second.data('name') || '';
                    const firstAssigned = parseInt($first.data('assigned'), 10);
                    const secondAssigned = parseInt($second.data('assigned'), 10);
                    const firstAssignedValue = Number.isNaN(firstAssigned) ? 0 : firstAssigned;
                    const secondAssignedValue = Number.isNaN(secondAssigned) ? 0 : secondAssigned;

                    if (assignedAssetSortMode === 'name_desc') {
                        return secondName.localeCompare(firstName);
                    }

                    if (assignedAssetSortMode === 'assigned_desc') {
                        return secondAssignedValue - firstAssignedValue || firstName.localeCompare(secondName);
                    }

                    if (assignedAssetSortMode === 'assigned_asc') {
                        return firstAssignedValue - secondAssignedValue || firstName.localeCompare(secondName);
                    }

                    return firstName.localeCompare(secondName);
                }

                function refreshAssignedAssetRows() {
                    const filters = getAssignedAssetFilters();
                    let visibleCount = 0;
                    const sortedRows = $assignedAssetRows.toArray().sort(compareAssignedAssetRows);

                    $.each(sortedRows, function(_, row) {
                        const $row = $(row);
                        const matchesSearch = !filters.search || normalizeText($row.data('search')).includes(filters.search);
                        const matchesCategory = !filters.category || $row.data('category') === filters.category;
                        const matchesSerial = filters.serial === '' || String($row.data('has-serial')) === filters.serial;
                        const isVisible = matchesSearch && matchesCategory && matchesSerial;

                        $row.toggleClass('d-none', !isVisible);
                        if (isVisible) {
                            visibleCount++;
                        }

                        $assignedAssetTbody.append(row);
                    });

                    if ($assignedAssetNoResultsRow.length) {
                        $assignedAssetTbody.append($assignedAssetNoResultsRow);
                        $assignedAssetNoResultsRow.toggleClass('d-none', visibleCount > 0);
                    }
                }

                const $assetRequestRows = $('.asset-request-row');
                const $assetRequestTbody = $('.asset-requests-table tbody');
                const $assetRequestNoResultsRow = $('#assetRequestNoResultsRow');
                let assetRequestSortMode = 'date_desc';
                let appliedAssetRequestFilters = {
                    category: '',
                    status: '',
                };

                function getAssetRequestFilters() {
                    return {
                        search: normalizeText($('#assetRequestSearchInput').val()),
                        category: appliedAssetRequestFilters.category,
                        status: appliedAssetRequestFilters.status,
                    };
                }

                function compareAssetRequestRows(firstRow, secondRow) {
                    const $first = $(firstRow);
                    const $second = $(secondRow);
                    const firstCategory = $first.data('category') || '';
                    const secondCategory = $second.data('category') || '';
                    const firstStatus = $first.data('status') || '';
                    const secondStatus = $second.data('status') || '';
                    const firstDate = parseInt($first.data('date'), 10);
                    const secondDate = parseInt($second.data('date'), 10);
                    const firstDateValue = Number.isNaN(firstDate) ? 0 : firstDate;
                    const secondDateValue = Number.isNaN(secondDate) ? 0 : secondDate;

                    if (assetRequestSortMode === 'date_asc') {
                        return firstDateValue - secondDateValue || firstCategory.localeCompare(secondCategory);
                    }

                    if (assetRequestSortMode === 'category_asc') {
                        return firstCategory.localeCompare(secondCategory) || secondDateValue - firstDateValue;
                    }

                    if (assetRequestSortMode === 'status_asc') {
                        return firstStatus.localeCompare(secondStatus) || secondDateValue - firstDateValue;
                    }

                    return secondDateValue - firstDateValue || firstCategory.localeCompare(secondCategory);
                }

                function refreshAssetRequestRows() {
                    const filters = getAssetRequestFilters();
                    let visibleCount = 0;
                    const sortedRows = $assetRequestRows.toArray().sort(compareAssetRequestRows);

                    $.each(sortedRows, function(_, row) {
                        const $row = $(row);
                        const matchesSearch = !filters.search || normalizeText($row.data('search')).includes(filters.search);
                        const matchesCategory = !filters.category || $row.data('category') === filters.category;
                        const matchesStatus = !filters.status || $row.data('status') === filters.status;
                        const isVisible = matchesSearch && matchesCategory && matchesStatus;

                        $row.toggleClass('d-none', !isVisible);
                        if (isVisible) {
                            visibleCount++;
                        }

                        $assetRequestTbody.append(row);
                    });

                    if ($assetRequestNoResultsRow.length) {
                        $assetRequestTbody.append($assetRequestNoResultsRow);
                        $assetRequestNoResultsRow.toggleClass('d-none', visibleCount > 0);
                    }
                }

                initDocumentFilterSelects();

                $('#documentSearchInput').on('input', refreshDocumentRows);
                $('#assignedAssetSearchInput').on('input', refreshAssignedAssetRows);
                $('#assetRequestSearchInput').on('input', refreshAssetRequestRows);

                $('#btnDocumentFilterApply').on('click', function() {
                    const $form = $('#documentFilterForm');
                    appliedDocumentFilters = {
                        status: $form.find('[name="status"]').val(),
                        hasExpiry: $form.find('[name="has_expiry"]').val(),
                    };

                    refreshDocumentRows();
                    $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-filter-dropdown.show').removeClass('show');
                });

                $('#btnDocumentFilterReset').on('click', function() {
                    setDocumentFilterChoice('status', '');
                    setDocumentFilterChoice('has_expiry', '');
                    appliedDocumentFilters = {
                        status: '',
                        hasExpiry: '',
                    };
                    refreshDocumentRows();
                });

                $('#btnAssignedAssetFilterApply').on('click', function() {
                    const $form = $('#assignedAssetFilterForm');
                    appliedAssignedAssetFilters = {
                        category: $form.find('[name="category"]').val(),
                        serial: $form.find('[name="serial"]').val(),
                    };

                    refreshAssignedAssetRows();
                    $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-filter-dropdown.show').removeClass('show');
                });

                $('#btnAssignedAssetFilterReset').on('click', function() {
                    $('#assignedAssetFilterForm [name="category"]').val('').trigger('change');
                    $('#assignedAssetFilterForm [name="serial"]').val('').trigger('change');
                    appliedAssignedAssetFilters = {
                        category: '',
                        serial: '',
                    };
                    refreshAssignedAssetRows();
                });

                $('#btnAssetRequestFilterApply').on('click', function() {
                    const $form = $('#assetRequestFilterForm');
                    appliedAssetRequestFilters = {
                        category: $form.find('[name="category"]').val(),
                        status: $form.find('[name="status"]').val(),
                    };

                    refreshAssetRequestRows();
                    $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-filter-dropdown.show').removeClass('show');
                });

                $('#btnAssetRequestFilterReset').on('click', function() {
                    $('#assetRequestFilterForm [name="category"]').val('').trigger('change');
                    $('#assetRequestFilterForm [name="status"]').val('').trigger('change');
                    appliedAssetRequestFilters = {
                        category: '',
                        status: '',
                    };
                    refreshAssetRequestRows();
                });

                $('.document-sort-link').on('click', function(e) {
                    e.preventDefault();
                    documentSortMode = $(this).data('sort') || 'title_asc';
                    $('.document-sort-link').removeClass('active').find('.feather-check').remove();
                    $(this).addClass('active').append('<i class="feather-check ms-3"></i>');
                    refreshDocumentRows();
                    $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-sort-dropdown.show').removeClass('show');
                });

                $('.assigned-asset-sort-link').on('click', function(e) {
                    e.preventDefault();
                    assignedAssetSortMode = $(this).data('sort') || 'name_asc';
                    $('.assigned-asset-sort-link').removeClass('active').find('.feather-check').remove();
                    $(this).addClass('active').append('<i class="feather-check ms-3"></i>');
                    refreshAssignedAssetRows();
                    $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-sort-dropdown.show').removeClass('show');
                });

                $('.asset-request-sort-link').on('click', function(e) {
                    e.preventDefault();
                    assetRequestSortMode = $(this).data('sort') || 'date_desc';
                    $('.asset-request-sort-link').removeClass('active').find('.feather-check').remove();
                    $(this).addClass('active').append('<i class="feather-check ms-3"></i>');
                    refreshAssetRequestRows();
                    $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                    $('.erp-sort-dropdown.show').removeClass('show');
                });

                refreshDocumentRows();
                refreshAssignedAssetRows();
                refreshAssetRequestRows();
            });
        </script>
    @endpush
@endsection
