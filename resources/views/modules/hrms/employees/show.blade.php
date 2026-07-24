@extends('layouts.duralux')

@section('title', __('hrms.employees.profile_title') . ' | SaaS ERP')
@section('page-title', __('hrms.employees.profile_title'))
@section('breadcrumb', 'HRMS / Employees / ' . __('hrms.employees.profile_title'))

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button href="{{ route('hrms.employees.index') }}" variant="light" icon="feather-arrow-left">
            {{ __('hrms.employees.back_to_registry') }}
        </x-ui.button>
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

        @php
            $rawTab = request('tab', request('active_tab', session('active_tab')));
            $activeTabName = $rawTab ? str_replace(['#', '-pane'], '', $rawTab) : 'overview';
        @endphp

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs tab-nav-custom" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'overview' ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="{{ $activeTabName === 'overview' ? 'true' : 'false' }}">
                    <i class="feather-user"></i> {{ __('hrms.employees.tab_overview') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'compensation' ? 'active' : '' }}" id="compensation-tab" data-bs-toggle="tab" data-bs-target="#compensation-pane" type="button" role="tab" aria-controls="compensation-pane" aria-selected="{{ $activeTabName === 'compensation' ? 'true' : 'false' }}">
                    <i class="feather-dollar-sign"></i> {{ __('hrms.employees.tab_compensation') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'leaves' ? 'active' : '' }}" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves-pane" type="button" role="tab" aria-controls="leaves-pane" aria-selected="{{ $activeTabName === 'leaves' ? 'true' : 'false' }}">
                    <i class="feather-calendar"></i> {{ __('hrms.employees.tab_leaves') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ in_array($activeTabName, ['penalization', 'penalties']) ? 'active' : '' }}" id="penalization-tab" data-bs-toggle="tab" data-bs-target="#penalization-pane" type="button" role="tab" aria-controls="penalization-pane" aria-selected="{{ in_array($activeTabName, ['penalization', 'penalties']) ? 'true' : 'false' }}">
                    <i class="feather-alert-triangle"></i> {{ __('hrms.employees.tab_penalties') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'documents' ? 'active' : '' }}" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab" aria-controls="documents-pane" aria-selected="{{ $activeTabName === 'documents' ? 'true' : 'false' }}">
                    <i class="feather-file-text"></i> {{ __('hrms.employees.tab_documents') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'history' ? 'active' : '' }}" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab" aria-controls="history-pane" aria-selected="{{ $activeTabName === 'history' ? 'true' : 'false' }}">
                    <i class="feather-clock"></i> {{ __('hrms.employees.tab_history') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'assets' ? 'active' : '' }}" id="assets-tab" data-bs-toggle="tab" data-bs-target="#assets-pane" type="button" role="tab" aria-controls="assets-pane" aria-selected="{{ $activeTabName === 'assets' ? 'true' : 'false' }}">
                    <i class="feather-package"></i> {{ __('hrms.employees.tab_assets') }}
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- 1. OVERVIEW TAB -->
            <div class="tab-pane fade {{ $activeTabName === 'overview' ? 'show active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                <div class="row">
                    <div class="col-md-6 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-briefcase text-primary"></i> {{ __('hrms.employees.lbl_employment_details') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.tbl_company') }}</div>
                                        <div class="info-value">{{ $employee->company?->company_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.org.business_units') }}</div>
                                        <div class="info-value">{{ $employee->businessUnit?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.org.branches') }}</div>
                                        <div class="info-value">{{ $employee->branch?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.tbl_department') }}</div>
                                        <div class="info-value">{{ $employee->department?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.tbl_designation') }}</div>
                                        <div class="info-value">{{ $employee->designation?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_doj') }}</div>
                                        <div class="info-value">{{ $employee->date_of_joining ? $employee->date_of_joining->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_emp_type') }}</div>
                                        <div class="info-value">{{ $employee->employment_type ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_stage') }}</div>
                                        <div class="info-value">{{ $employee->employee_stage ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_manager') }}</div>
                                        <div class="info-value">{{ $employee->reportingManager?->full_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.org.shifts') }}</div>
                                        <div class="info-value">{{ $employee->shift?->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_office_email') }}</div>
                                        <div class="info-value">{{ $employee->office_email ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_probation_end') }}</div>
                                        <div class="info-value">{{ $employee->probation_end_date ? $employee->probation_end_date->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_confirmation_date') }}</div>
                                        <div class="info-value">{{ $employee->confirmation_date ? $employee->confirmation_date->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-user text-primary"></i> {{ __('hrms.employees.lbl_personal_details') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_gender') }}</div>
                                        <div class="info-value">{{ $employee->gender }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_marital_status') }}</div>
                                        <div class="info-value">{{ $employee->marital_status ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_blood_group') }}</div>
                                        <div class="info-value">{{ $employee->blood_group ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_diet_preference') }}</div>
                                        <div class="info-value">{{ $employee->diet_preference ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_aadhaar') }}</div>
                                        <div class="info-value">{{ $employee->aadhaar_card_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_pan') }}</div>
                                        <div class="info-value">{{ $employee->pan_card_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-label">{{ __('hrms.employees.lbl_dob') }}</div>
                                        <div class="info-value">{{ $employee->date_of_birth ? $employee->date_of_birth->format('d M, Y') : 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-map-pin text-primary"></i> {{ __('hrms.employees.lbl_contact_address') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_personal_mobile') }}</div>
                                        <div class="info-value">{{ $employee->personal_mobile_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_home_phone') }}</div>
                                        <div class="info-value">{{ $employee->home_phone ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_city_postal') }}</div>
                                        <div class="info-value">{{ $employee->city ?: 'N/A' }} {{ $employee->postal_code ? '(' . $employee->postal_code . ')' : '' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_emergency_contact') }}</div>
                                        <div class="info-value">{{ $employee->emergency_contact_name ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_emergency_number') }}</div>
                                        <div class="info-value">{{ $employee->emergency_contact_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_emergency_relation') }}</div>
                                        <div class="info-value">{{ $employee->emergency_contact_relation ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_present_address') }}</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->present_address ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_permanent_address') }}</div>
                                        <div class="info-value text-wrap" style="max-width: 100%;">{{ $employee->permanent_address ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-credit-card text-primary"></i> {{ __('hrms.employees.lbl_bank_details') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_bank_name') }}</div>
                                        <div class="info-value">{{ $employee->bank_name ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_account_number') }}</div>
                                        <div class="info-value">{{ $employee->account_number ?: 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="info-label">{{ __('hrms.employees.lbl_ifsc_code') }}</div>
                                        <div class="info-value">{{ $employee->ifsc_code ?: 'N/A' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. COMPENSATION & SALARY TAB -->
            <div class="tab-pane fade {{ $activeTabName === 'compensation' ? 'show active' : '' }}" id="compensation-pane" role="tabpanel" aria-labelledby="compensation-tab">
                <div class="row g-4">
                    <!-- Left: Slab & Structure Breakdown -->
                    <div class="col-lg-8 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <div>
                                    <h5 class="card-custom-title"><i class="feather-dollar-sign text-primary"></i> {{ __('hrms.employees.lbl_computed_salary') }}</h5>
                                    <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_computed_salary_desc') }}</small>
                                </div>
                                @if($salaryStructure)
                                    <span class="badge bg-soft-primary text-primary px-3 py-2 rounded-pill fs-12">
                                        {{ $salaryStructure->name }}
                                    </span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-3 py-2 rounded-pill fs-12">
                                        {{ __('hrms.employees.lbl_no_slab') }}
                                    </span>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                @if(!$employee->payGroup)
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-alert-circle fs-32 d-block mb-3 text-warning"></i>
                                        <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_pay_group') }}</div>
                                        <div>{{ __('hrms.employees.lbl_no_pay_group_desc') }}</div>
                                    </div>
                                @elseif(!$salaryStructure)
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-alert-octagon fs-32 d-block mb-3 text-danger"></i>
                                        <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_slab_match') }}</div>
                                        <div>The employee's CTC (₹{{ number_format($employee->current_salary, 2) }}) does not fall inside any Salary Structure slab configured for the <strong>{{ $employee->payGroup->name }}</strong> Pay Group.</div>
                                    </div>
                                @else
                                    <!-- Component Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>{{ __('hrms.employees.tbl_code_component') }}</th>
                                                    <th>{{ __('hrms.assets.status') }}</th>
                                                    <th>{{ __('hrms.employees.tbl_formula') }}</th>
                                                    <th class="text-end">{{ __('hrms.employees.tbl_monthly') }}</th>
                                                    <th class="text-end">{{ __('hrms.employees.tbl_yearly') }}</th>
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
                                                    <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_total_earnings') }}</td>
                                                    <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly, 2) }}</td>
                                                    <td class="text-end fw-bold text-success">₹{{ number_format($totalEarningsMonthly * 12, 2) }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_total_deductions') }}</td>
                                                    <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly, 2) }}</td>
                                                    <td class="text-end fw-bold text-danger">₹{{ number_format($totalDeductionsMonthly * 12, 2) }}</td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="3" class="fw-bold">{{ __('hrms.employees.lbl_net_salary') }}</td>
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
                                <h5 class="card-custom-title"><i class="feather-info text-primary"></i> {{ __('hrms.employees.lbl_master_summary') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex flex-column gap-3">
                                    <div>
                                        <div class="info-label">Pay Group</div>
                                        <div class="info-value text-dark">{{ $employee->payGroup?->name ?? 'Not Assigned' }}</div>
                                    </div>
                                    <div>
                                        <div class="info-label">{{ __('hrms.employees.lbl_annual_ctc') }}</div>
                                        <div class="info-value text-primary fs-18 fw-bold">₹{{ number_format($employee->current_salary, 2) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-plus-circle text-primary"></i> {{ __('hrms.employees.lbl_monthly_adhoc') }}</h5>
                                <x-ui.button 
                                    type="button" 
                                    variant="soft-primary" 
                                    size="sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#addAdhocModal" 
                                    :disabled="!$employee->pay_group_id"
                                >
                                    {{ __('hrms.common.add') }}
                                </x-ui.button>
                            </div>
                            <div class="card-body p-0">
                                @if($adhocComponents->isEmpty())
                                    <div class="p-4 text-center text-muted fs-13">
                                        {{ __('hrms.employees.lbl_no_adhoc_components') }}
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                                             <thead class="table-light">
                                                 <tr>
                                                     <th>{{ __('hrms.employees.tbl_component') }}</th>
                                                     <th class="text-end">{{ __('hrms.employees.tbl_amount') }}</th>
                                                     <th class="text-end">{{ __('hrms.employees.tbl_action') }}</th>
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
                                                             <form action="{{ route('hrms.employees.adhoc-components.destroy', $adhoc->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.employees.confirm_delete_adhoc') }}', { title: 'Delete Adhoc Component', variant: 'danger', confirmButtonText: 'Delete' });">
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
            <div class="tab-pane fade {{ $activeTabName === 'leaves' ? 'show active' : '' }}" id="leaves-pane" role="tabpanel" aria-labelledby="leaves-tab">
                <div class="row g-4">
                    <!-- LEFT COLUMN: Assigned Leave Plan & Brief Allowances -->
                    <div class="col-lg-4 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-info text-primary me-1.5"></i> {{ __('hrms.employees.lbl_assigned_leave_plan') }}</h5>
                            </div>
                            <div class="card-body p-3">
                                @if($employee->leavePlan)
                                    @if(!$employee->leavePlan->status)
                                        <div class="alert alert-warning py-1.5 px-3 mb-2 fs-12 d-flex align-items-center gap-2 border-0" style="background-color: #fef3c7; color: #92400e;">
                                            <i class="feather-alert-triangle"></i>
                                            <span>This leave plan is currently inactive.</span>
                                        </div>
                                    @endif
                                    <div class="mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="fw-bold mb-0 text-dark fs-14">{{ $employee->leavePlan->name }}</h6>
                                            @if(!$employee->leavePlan->status)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 px-2 py-0.5 rounded">Inactive</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle fs-10 px-2 py-0.5 rounded">Active</span>
                                            @endif
                                        </div>
                                        <p class="text-muted fs-12 mb-0 mt-1">{{ $employee->leavePlan->description ?: 'No description provided.' }}</p>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center my-3">
                                        <span class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.employees.lbl_effective_from') }}</span>
                                        <span class="fw-bold text-dark fs-12">{{ $employee->leavePlan->effective_from ? $employee->leavePlan->effective_from->format('d M, Y') : 'N/A' }}</span>
                                    </div>

                                    <!-- Compact Leave Allowances Table (Without Division Lines & Large Gaps) -->
                                    @if(!$employee->leavePlan->types->isEmpty())
                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="py-1">TYPE NAME</th>
                                                        <th class="text-center py-1">BALANCE</th>
                                                        <th class="text-end py-1">RULES</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($employee->leavePlan->types as $ltype)
                                                        @php
                                                            $balance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $employee->id)
                                                                ->where('leave_type_id', $ltype->id)
                                                                ->first();
                                                            $balanceVal = $balance ? floatval($balance->remaining) : 0.0;
                                                            $allocatedVal = $balance ? floatval($balance->allocated) : floatval($ltype->quota);
                                                        @endphp
                                                        <tr>
                                                            <td class="py-1">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="d-inline-block rounded-circle flex-shrink-0 me-1" style="width: 8px; height: 8px; background-color: {{ $ltype->color ?: '#3b82f6' }};"></span>
                                                                    <span class="fw-bold text-dark fs-12">{{ $ltype->name }}</span>
                                                                    <span class="text-muted fs-10 text-uppercase ms-1" style="font-size: 10px;">{{ $ltype->code }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="text-center py-1" style="white-space: nowrap;">
                                                                <span class="fw-bold text-dark fs-12">{{ $balanceVal }}</span>
                                                                <span class="text-muted fs-10">/ {{ $allocatedVal }}</span>
                                                            </td>
                                                            <td class="text-end py-1">
                                                                <button
                                                                    type="button"
                                                                    class="leave-rules-icon-btn btn btn-light border d-inline-flex align-items-center justify-content-center p-0 rounded"
                                                                    style="width: 22px; height: 22px;"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#leaveRulesModal{{ $ltype->id }}"
                                                                    title="View leave rules"
                                                                    aria-label="View rules for {{ $ltype->name }}"
                                                                >
                                                                    <i class="feather-sliders text-primary" style="font-size: 10px;"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <!-- Action Buttons: Apply Leave & Apply Encashment -->
                                    <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                                        <button type="button"
                                            class="btn btn-sm btn-primary flex-grow-1 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1"
                                            data-bs-toggle="modal" data-bs-target="#empApplyLeaveModal">
                                            <i class="feather-plus fs-12"></i> Apply Leave
                                        </button>
                                        <button type="button"
                                            class="btn btn-sm btn-primary flex-grow-1 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1"
                                            data-bs-toggle="modal" data-bs-target="#empApplyEncashmentModal">
                                            <i class="feather-dollar-sign fs-12"></i> Encashment
                                        </button>
                                    </div>
                                @else
                                    <div class="text-center py-3 text-muted fs-13">
                                        <i class="feather-alert-circle d-block fs-24 mb-2 text-warning"></i>
                                        {{ __('hrms.employees.lbl_no_leave_plan') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Leave Applications & History / Encashment Toggle Container -->
                    <div class="col-lg-8 col-12">
                        @php
                            $empLeaveRequests = $leaveRequests ?? \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
                            $empLeaveEncashments = $leaveEncashments ?? \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
                            $isAdminUser      = auth()->user() && auth()->user()->hasHrPermission('hr.settings.manage');
                            $allLeaveTypes    = $employee->leavePlan ? $employee->leavePlan->types : \App\Domains\HRMS\Models\LeaveType::where('is_active', true)->orderBy('name')->get();
                        @endphp

                        <!-- ABOVE THE ENCASHMENT CARD BOX (Top Right Corner) -->
                        <div class="d-flex align-items-center justify-content-end mb-3 gap-2 flex-wrap">
                            <!-- 1. Toolbar for Leave Applications Search, Sort, Filter -->
                            <div id="leaveAppsToolbar" class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                                <!-- Registry Style Search Input -->
                                <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 180px; max-width: 240px; height: 38px;">
                                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                    <input type="text" id="empLeaveAppSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') ?? 'Search applications...' }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                                </div>

                                <!-- Sort Dropdown with Checkmark Icons -->
                                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                                        <span>{{ __('hrms.leave.app.sort_newest') ?? 'Newest First' }}</span>
                                        <i class="feather-check text-dark ms-auto sort-check"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                                        <span>{{ __('hrms.leave.app.sort_oldest') ?? 'Oldest First' }}</span>
                                        <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_desc">
                                        <span>{{ __('hrms.leave.app.sort_duration_high_low') ?? 'Duration (High to Low)' }}</span>
                                        <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_asc">
                                        <span>{{ __('hrms.leave.app.sort_duration_low_high') ?? 'Duration (Low to High)' }}</span>
                                        <i class="feather-check text-dark ms-auto sort-check d-none"></i>
                                    </a>
                                </x-ui.sort-dropdown>

                                <!-- Filter Dropdown -->
                                <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                    <form id="empLeaveAppFilterForm" onsubmit="return false;">
                                        <div class="mb-3" style="min-width: 220px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="empLeaveAppFilterStatus">
                                                <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                                <option value="unauthorized">Unauthorized</option>
                                                <option value="unpaid">Unpaid</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3" style="min-width: 220px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Leave Type</label>
                                            <x-ui.odoo-form-ui type="select" name="leave_type_id" id="empLeaveAppFilterType">
                                                <option value="">All Types</option>
                                                @foreach($allLeaveTypes as $lt)
                                                    <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="dropdown-divider my-3"></div>
                                        <div class="d-flex gap-2">
                                            <x-ui.button type="button" id="btnEmpLeaveAppFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') ?? 'Apply' }}</x-ui.button>
                                            <x-ui.button type="button" id="btnEmpLeaveAppFilterReset" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') ?? 'Reset' }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.filter>
                            </div>

                            <!-- 2. Toolbar for Leave Encashments Search, Sort, Filter -->
                            <div id="leaveEncashmentsToolbar" class="d-flex align-items-center gap-2 flex-wrap d-none ms-auto">
                                <!-- Registry Style Search Input -->
                                <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 180px; max-width: 240px; height: 38px;">
                                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                    <input type="text" id="empLeaveEncSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.leave.app.search_employee') ?? 'Search encashments...' }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                                </div>

                                <!-- Sort Dropdown with Checkmark Icons -->
                                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                                        <span>{{ __('hrms.leave.encashment_app.sort_newest') ?? 'Newest First' }}</span>
                                        <i class="feather-check text-dark ms-auto encash-sort-check"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                                        <span>{{ __('hrms.leave.encashment_app.sort_oldest') ?? 'Oldest First' }}</span>
                                        <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="days_desc">
                                        <span>{{ __('hrms.leave.encashment_app.sort_days_high_low') ?? 'Days (High to Low)' }}</span>
                                        <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                    </a>
                                    <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="days_asc">
                                        <span>{{ __('hrms.leave.encashment_app.sort_days_low_high') ?? 'Days (Low to High)' }}</span>
                                        <i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                                    </a>
                                </x-ui.sort-dropdown>

                                <!-- Filter Dropdown -->
                                <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                    <form id="empLeaveEncFilterForm" onsubmit="return false;">
                                        <div class="mb-3" style="min-width: 220px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('ui.status') ?? 'Status' }}</label>
                                            <x-ui.odoo-form-ui type="select" name="status" id="empLeaveEncFilterStatus">
                                                <option value="">{{ __('hrms.leave.encashment_app.all_statuses') }}</option>
                                                <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                                <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                                <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3" style="min-width: 220px;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Leave Type</label>
                                            <x-ui.odoo-form-ui type="select" name="leave_type_id" id="empLeaveEncFilterType">
                                                <option value="">All Types</option>
                                                @foreach($allLeaveTypes as $lt)
                                                    <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="dropdown-divider my-3"></div>
                                        <div class="d-flex gap-2">
                                            <x-ui.button type="button" id="btnEmpLeaveEncFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.common.apply') ?? 'Apply' }}</x-ui.button>
                                            <x-ui.button type="button" id="btnEmpLeaveEncFilterReset" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.common.reset') ?? 'Reset' }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.filter>
                            </div>
                        </div>

                        <!-- MAIN CARD BOX -->
                        <div class="card-custom">
                            <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div id="leaveAppsHeaderTitle" class="d-flex align-items-center gap-2">
                                        <h5 class="card-custom-title mb-0">
                                            <i class="feather-calendar text-primary me-1.5"></i> Leave Applications & History
                                        </h5>
                                        <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 ms-1 fw-bold">
                                            {{ $empLeaveRequests->count() }} {{ $empLeaveRequests->count() === 1 ? 'Application' : 'Applications' }}
                                        </span>
                                    </div>
                                    <div id="leaveEncashmentsHeaderTitle" class="d-flex align-items-center gap-2 d-none">
                                        <h5 class="card-custom-title mb-0">
                                            <i class="feather-dollar-sign text-primary me-1.5"></i> Leave Encashments
                                        </h5>
                                        <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 ms-1 fw-bold">
                                            {{ $empLeaveEncashments->count() }} {{ $empLeaveEncashments->count() === 1 ? 'Encashment' : 'Encashments' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <!-- View Toggle Button (Just opposite to Header Title using common x-ui.button) -->
                                    <x-ui.button 
                                        type="button" 
                                        id="btnToggleLeaveView" 
                                        variant="soft-primary" 
                                        size="sm" 
                                        class="fw-bold text-uppercase" 
                                        style="font-size: 11px;"
                                    >
                                        <span id="toggleBtnLabel"><i class="feather-dollar-sign me-1"></i> Encashment Details</span>
                                    </x-ui.button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- 1. LEAVE APPLICATIONS VIEW -->
                                <div id="leaveApplicationsViewContainer">
                                    @if($empLeaveRequests->isEmpty())
                                        <div class="p-5 text-center text-muted">
                                            <i class="feather-calendar fs-24 text-secondary d-block mb-2"></i>
                                            No leave applications submitted by this employee yet.
                                        </div>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0" id="leaveAppTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="min-width:130px;">Leave Type</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:160px;">Period</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:80px;">Days</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:95px;">Status</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:70px;">File</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width:70px;">Detail</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($empLeaveRequests as $req)
                                                        @php
                                                            $sameYear = $req->start_date && $req->end_date && $req->start_date->format('Y') === $req->end_date->format('Y');
                                                            $startStr = $req->start_date ? $req->start_date->format($sameYear ? 'd M' : 'd M Y') : '-';
                                                            $endStr   = $req->end_date   ? $req->end_date->format('d M Y')   : '-';
                                                            $dateRange = ($req->start_date && $req->end_date && $req->start_date->isSameDay($req->end_date))
                                                                ? $req->start_date->format('d M Y')
                                                                : $startStr . ' – ' . $endStr;

                                                            $statusBadge = match($req->status) {
                                                                'approved'     => ['cls' => 'bg-soft-success text-success',  'icon' => 'feather-check-circle', 'lbl' => 'Approved'],
                                                             'pending'      => ['cls' => 'bg-soft-warning text-warning',  'icon' => 'feather-clock',         'lbl' => 'Pending'],
                                                                'rejected'     => ['cls' => 'bg-soft-danger text-danger',    'icon' => 'feather-x-circle',      'lbl' => 'Rejected'],
                                                                'unauthorized' => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',        'lbl' => 'Unauthorized'],
                                                                'unpaid'       => ['cls' => 'bg-soft-info text-info',        'icon' => 'feather-alert-circle',  'lbl' => 'Unpaid'],
                                                                default        => ['cls' => 'bg-light text-secondary',       'icon' => 'feather-circle',        'lbl' => ucfirst($req->status)],
                                                            };

                                                            $rowBalance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $req->employee_id)
                                                                ->where('leave_type_id', $req->leave_type_id)
                                                                ->first();
                                                            $rowRemaining = $rowBalance ? floatval($rowBalance->remaining) : 0.0;
                                                            $rowAllocated = $rowBalance ? floatval($rowBalance->allocated) : floatval($req->leaveType?->quota ?: 0);

                                                            $notifiedNames = '';
                                                            if (!empty($req->notified_contacts)) {
                                                                $contacts = \App\Domains\HRMS\Models\Employee::whereIn('id', $req->notified_contacts)->pluck('full_name')->toArray();
                                                                $notifiedNames = implode(', ', $contacts);
                                                            }
                                                        @endphp
                                                        <tr class="leave-app-row"
                                                            style="cursor:pointer;"
                                                            data-req-id="{{ $req->id }}"
                                                            data-leave-type="{{ strtolower($req->leaveType?->name ?: 'n/a') }}"
                                                            data-leave-type-id="{{ $req->leave_type_id }}"
                                                            data-leave-code="{{ strtolower($req->leaveType?->code ?? '') }}"
                                                            data-leave-color="{{ $req->leaveType?->color ?: '#3b82f6' }}"
                                                            data-date-range="{{ $dateRange }}"
                                                            data-start="{{ $req->start_date?->format('d M Y') }}"
                                                            data-end="{{ $req->end_date?->format('d M Y') }}"
                                                            data-start-type="{{ str_replace('_',' ', $req->start_date_type) }}"
                                                            data-end-type="{{ str_replace('_',' ', $req->end_date_type) }}"
                                                            data-duration="{{ floatval($req->duration) }}"
                                                            data-reason="{{ strtolower(addslashes($req->reason ?? '')) }}"
                                                            data-status="{{ strtolower($req->status) }}"
                                                            data-status-label="{{ $statusBadge['lbl'] }}"
                                                            data-status-cls="{{ $statusBadge['cls'] }}"
                                                            data-status-icon="{{ $statusBadge['icon'] }}"
                                                            data-applied="{{ $req->created_at?->format('d M Y, h:i A') }}"
                                                            data-created-at="{{ $req->created_at?->timestamp ?: 0 }}"
                                                            data-rejection="{{ addslashes($req->rejection_reason ?? '') }}"
                                                            data-attachment="{{ $req->attachment_path ? asset('storage/'.$req->attachment_path) : '' }}"
                                                            data-workflow="{{ $req->status === 'approved' ? (__('hrms.leave.app.status_approved') ?? 'Approved') : ($req->status === 'rejected' ? (__('hrms.leave.app.status_rejected') ?? 'Rejected') : (in_array($req->status,['unauthorized','unpaid']) ? (__('hrms.leave.app.processed') ?? 'Processed') : (__('hrms.leave.app.level_n', ['level' => $req->current_level]) ?? ('Level ' . $req->current_level)))) }}"
                                                            data-update-url="{{ route('hrms.leaves.update-status', $req->id) }}"
                                                            data-notified-names="{{ $notifiedNames }}"
                                                            data-remaining="{{ $rowRemaining }}"
                                                            data-allocated="{{ $rowAllocated }}"
                                                        >
                                                            <td class="ps-3">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="flex-shrink-0 rounded-circle" style="width:9px;height:9px;background:{{ $req->leaveType?->color ?: '#3b82f6' }};display:inline-block;"></span>
                                                                    <div>
                                                                        <div class="fw-semibold text-dark fs-13">{{ $req->leaveType?->name ?: 'N/A' }}</div>
                                                                        <code class="fs-10 text-muted">{{ $req->leaveType?->code }}</code>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="fs-13 text-dark">{{ $dateRange }}</div>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="fw-bold fs-13 text-dark">{{ floatval($req->duration) }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2 py-1 fs-11">
                                                                    <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                @if($req->attachment_path)
                                                                    <i class="feather-paperclip text-primary fs-13" title="Has attachment" data-bs-toggle="tooltip"></i>
                                                                @else
                                                                    <span class="text-muted fs-13">—</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-end pe-3">
                                                                <button type="button"
                                                                    class="btn btn-sm btn-light border open-leave-detail px-2 py-1"
                                                                    title="View Details"
                                                                    data-bs-toggle="offcanvas"
                                                                    data-bs-target="#leaveDetailDrawer">
                                                                    <i class="feather-eye fs-12 text-primary"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr id="no_matching_emp_leave_apps_row" class="d-none">
                                                        <td colspan="10" class="text-center py-5 text-muted">
                                                            <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                                            No matching leave applications found.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Leave Applications Pagination Container -->
                                        <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empLeaveAppsPaginationContainer">
                                            <ul class="erp-pagination mb-2 justify-content-center" id="emp_leave_apps_pagination_ul">
                                                <!-- Dynamically generated pagination links -->
                                            </ul>
                                            <div class="erp-pagination-info text-center">
                                                Showing <span id="emp_leave_apps_showing_start">0</span> to <span id="emp_leave_apps_showing_end">0</span> of <strong id="emp_leave_apps_total_count">0</strong> entries
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- 2. LEAVE ENCASHMENTS VIEW -->
                                <div id="leaveEncashmentsViewContainer" class="d-none">
                                    @if($empLeaveEncashments->isEmpty())
                                        <div class="p-5 text-center text-muted">
                                            <i class="feather-dollar-sign fs-24 text-secondary d-block mb-2"></i>
                                            No leave encashment requests submitted by this employee yet.
                                        </div>
                                    @else
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0" id="empLeaveEncashmentTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold ps-3">Leave Type</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold text-center">Requested Days</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold">Reason</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold">Submitted Date</th>
                                                        <th class="fs-12 text-uppercase text-muted fw-semibold">Status</th>
                                                        @if($isAdminUser)
                                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3">Actions</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($empLeaveEncashments as $enc)
                                                        @php
                                                            $encStatusBadge = match($enc->status) {
                                                                'approved' => ['cls' => 'bg-soft-success text-success', 'icon' => 'feather-check-circle', 'lbl' => 'Approved'],
                                                                'pending'  => ['cls' => 'bg-soft-warning text-warning', 'icon' => 'feather-clock',        'lbl' => 'Pending'],
                                                                'rejected' => ['cls' => 'bg-soft-danger text-danger',   'icon' => 'feather-x-circle',     'lbl' => 'Rejected'],
                                                                default    => ['cls' => 'bg-light text-secondary',      'icon' => 'feather-circle',       'lbl' => ucfirst($enc->status)],
                                                            };
                                                        @endphp
                                                        <tr class="emp-encash-row"
                                                            data-enc-id="{{ $enc->id }}"
                                                            data-leave-type="{{ strtolower($enc->leaveType?->name ?: 'n/a') }}"
                                                            data-leave-type-id="{{ $enc->leave_type_id }}"
                                                            data-reason="{{ strtolower(addslashes($enc->reason ?? '')) }}"
                                                            data-status="{{ strtolower($enc->status) }}"
                                                            data-days="{{ floatval($enc->requested_days) }}"
                                                            data-created-at="{{ $enc->created_at?->timestamp ?: 0 }}"
                                                        >
                                                            <td class="ps-3">
                                                                <span class="badge bg-light text-primary fw-semibold fs-12">{{ $enc->leaveType?->name ?: 'N/A' }}</span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="fw-bold fs-13 text-dark">{{ floatval($enc->requested_days) }} {{ __('hrms.leave.days') }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="fs-12 text-muted">{{ $enc->reason ?: 'No reason provided.' }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="fs-12 text-dark">{{ $enc->created_at ? $enc->created_at->format('d M Y') : '—' }}</span>
                                                            </td>
                                                            <td>
                                                                <span class="badge {{ $encStatusBadge['cls'] }} rounded-pill px-2 py-1 fs-11">
                                                                    <i class="{{ $encStatusBadge['icon'] }} me-1"></i>{{ $encStatusBadge['lbl'] }}
                                                                </span>
                                                            </td>
                                                            @if($isAdminUser)
                                                                <td class="text-end pe-3">
                                                                    @if($enc->status === 'pending')
                                                                        <form method="POST" action="{{ route('hrms.leaves.encashment.approve', $enc->id) }}" class="d-inline-block me-1">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-success px-2 py-1 fs-11 fw-semibold" title="Approve Encashment"><i class="feather-check me-1"></i> Approve</button>
                                                                        </form>
                                                                        <form method="POST" action="{{ route('hrms.leaves.encashment.reject', $enc->id) }}" class="d-inline-block">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1 fs-11 fw-semibold" title="Reject Encashment"><i class="feather-x me-1"></i> Reject</button>
                                                                        </form>
                                                                    @else
                                                                        <form method="POST" action="{{ route('hrms.leaves.encashment.destroy', $enc->id) }}" class="d-inline-block" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this encashment request?', { title: 'Delete Encashment', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" class="btn btn-sm btn-light border text-danger px-2 py-1 fs-11" title="Delete"><i class="feather-trash-2"></i></button>
                                                                        </form>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                    <tr id="no_matching_emp_leave_enc_row" class="d-none">
                                                        <td colspan="10" class="text-center py-5 text-muted">
                                                            <i class="feather-dollar-sign fs-3 d-block mb-2 text-secondary"></i>
                                                            No matching leave encashment requests found.
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Leave Encashments Pagination Container -->
                                        <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empLeaveEncPaginationContainer">
                                            <ul class="erp-pagination mb-2 justify-content-center" id="emp_leave_enc_pagination_ul">
                                                <!-- Dynamically generated encashment pagination links -->
                                            </ul>
                                            <div class="erp-pagination-info text-center">
                                                Showing <span id="emp_leave_enc_showing_start">0</span> to <span id="emp_leave_enc_showing_end">0</span> of <strong id="emp_leave_enc_total_count">0</strong> entries
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                                    {{-- Leave Detail Offcanvas Drawer --}}
                                    <x-ui.drawer id="leaveDetailDrawer" title="Leave Application Detail" style="width:420px;max-width:100%;">
                                        {{-- Leave Type Banner (with balance) --}}
                                        <div class="d-flex align-items-start gap-3 mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                            <span id="ld-color-dot" class="rounded-circle flex-shrink-0 mt-1" style="width:12px;height:12px;display:inline-block;"></span>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold fs-14 text-dark" id="ld-leave-type">—</div>
                                                <div class="fs-12 text-muted mt-1" id="ld-balance-inline"></div>
                                                <div class="fs-11 text-muted mt-1">Applied On: <span class="fw-semibold text-dark" id="ld-applied">—</span></div>
                                            </div>
                                            <span class="badge rounded-pill px-2 py-1 fs-11 flex-shrink-0" id="ld-status-badge"></span>
                                        </div>

                                        {{-- Period & Duration --}}
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Period</div>
                                                <div class="fw-semibold text-dark fs-13" id="ld-date-range">—</div>
                                                <div class="text-muted fs-12 mt-1" id="ld-session-info"></div>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Duration</div>
                                                <div class="fw-bold fs-22 text-primary" id="ld-duration">—</div>
                                            </div>
                                        </div>

                                        <hr class="my-3">

                                        {{-- Reason --}}
                                        <div class="mb-3">
                                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Reason</div>
                                            <div class="fs-13 text-dark" id="ld-reason" style="white-space:pre-line;">—</div>
                                        </div>

                                        {{-- Rejection Reason --}}
                                        <div class="mb-3 d-none" id="ld-rejection-wrap">
                                            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Rejection Reason</div>
                                            <div class="alert alert-soft-danger py-2 px-3 fs-13 mb-0" id="ld-rejection"></div>
                                        </div>
                                         {{-- Workflow Level & Attachment --}}
                                         <div class="d-flex justify-content-between align-items-center mb-3">
                                             <div>
                                                 <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Workflow Level</div>
                                                 <div class="fs-13 text-dark" id="ld-workflow">—</div>
                                             </div>
                                             <div class="d-none text-end" id="ld-attach-wrap">
                                                 <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Attachment</div>
                                                 <a id="ld-attach-link" href="#" target="_blank" class="btn btn-sm btn-soft-primary d-inline-flex align-items-center gap-1">
                                                     <i class="feather-paperclip fs-12"></i> View Attachment
                                                 </a>
                                             </div>
                                         </div>

                                         {{-- Notified Members --}}
                                         <div class="mb-3 d-none" id="ld-notified-wrap">
                                             <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Notified Members</div>
                                             <div class="fs-13 text-dark" id="ld-notified-names">—</div>
                                         </div>

                                        {{-- Status Change --}}
                                        @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                            <hr class="my-3">
                                            <div>
                                                <div class="text-muted fs-11 text-uppercase fw-semibold mb-2" style="letter-spacing:.5px;">Update Status</div>
                                                <form method="POST" id="ld-status-form" action="">
                                                    @csrf
                                                    <div class="d-flex gap-2 align-items-center">
                                                        <div class="flex-grow-1" style="margin-bottom: -1rem;">
                                                            <x-ui.select name="status" id="ld-status-select" class="odoo-select2">
                                                                <option value="pending">Pending</option>
                                                                <option value="approved">Approved</option>
                                                                <option value="rejected">Rejected</option>
                                                                <option value="unauthorized">Unauthorized</option>
                                                                <option value="unpaid">Unpaid</option>
                                                            </x-ui.select>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1" style="height: 38px; border-radius: 6px;">
                                                            <i class="feather-check fs-12"></i> Apply
                                                        </button>
                                                    </div>
                                                    <div class="mt-2 d-none" id="ld-rejection-input-wrap">
                                                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-2 mt-2" style="letter-spacing:.5px;">Rejection Reason</div>
                                                        <x-ui.textarea name="rejection_reason" id="ld-rejection-reason-input" rows="2" placeholder="Enter reason for rejection..." />
                                                    </div>
                                                </form>
                                            </div>
                                        @endif

                                        <x-slot:footer>
                                            <button type="button" class="btn btn-light border fw-semibold text-uppercase" data-bs-dismiss="offcanvas">CLOSE PANEL</button>
                                        </x-slot:footer>
                                    </x-ui.drawer>
                        </div>
                    </div>
                </div>

            <!-- 4. PENALIZATION TAB -->
            <div class="tab-pane fade {{ in_array($activeTabName, ['penalization', 'penalties']) ? 'show active' : '' }}" id="penalization-pane" role="tabpanel" aria-labelledby="penalization-tab">
                <div class="row g-4">
                    <!-- Left: Applied Penalties Log -->
                    <div class="col-lg-8 col-12">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="card-custom-title"><i class="feather-list text-primary"></i> {{ __('hrms.employees.lbl_penalization_history') }}</h5>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addPenaltyModal" @disabled(!$attendancePenalty)>
                                    {{ __('hrms.employees.btn_log_instance') }}
                                </button>
                            </div>
                            <div class="card-body p-0">
                                @if($penalties->isEmpty())
                                    <div class="p-5 text-center text-muted">
                                        <i class="feather-check-circle fs-32 d-block mb-3 text-success"></i>
                                        <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_penalties') }}</div>
                                        <div>{{ __('hrms.employees.lbl_no_penalties_desc') }}</div>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>{{ __('hrms.employees.tbl_date_occurred') }}</th>
                                                    <th>{{ __('hrms.employees.tbl_rule_violation') }}</th>
                                                    <th>{{ __('hrms.employees.tbl_status') }}</th>
                                                    <th>{{ __('hrms.employees.tbl_month') }}</th>
                                                    <th class="text-end">{{ __('hrms.employees.tbl_deduction_penalty') }}</th>
                                                    <th class="text-end">{{ __('hrms.employees.tbl_actions') }}</th>
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
                                                            <form action="{{ route('hrms.employees.penalties.destroy', $penalty->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Delete this penalty log?', { title: 'Delete Penalty Log', variant: 'danger', confirmButtonText: 'Delete' });">
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
                                <h5 class="card-custom-title"><i class="feather-shield text-primary"></i> {{ __('hrms.employees.lbl_penalization_policy') }}</h5>
                            </div>
                            <div class="card-body p-4">
                                @if($attendancePenalties && $attendancePenalties->isNotEmpty())
                                    @foreach($attendancePenalties as $index => $policy)
                                        <div class="policy-info-pane {{ $index === 0 ? '' : 'd-none' }}" data-index="{{ $index }}" data-policy-type="{{ $policy->rule_type }}">
                                            <div class="mb-4 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="info-label">{{ __('hrms.employees.lbl_active_rule_type') }}</div>
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
                                                        <div class="info-label">{{ __('hrms.employees.lbl_grace_period') }}</div>
                                                        <div class="info-value">{{ $policy->grace_period_minutes }} Mins</div>
                                                    </div>
                                                @elseif($policy->rule_type === 'under_hours')
                                                    <div class="col-6">
                                                        <div class="info-label">{{ __('hrms.employees.lbl_daily_target') }}</div>
                                                        <div class="info-value">{{ floatval($policy->grace_period_minutes / 60) }} Hours</div>
                                                    </div>
                                                @else
                                                    <div class="col-6">
                                                        <div class="info-label">{{ __('hrms.employees.lbl_grace_period') }}</div>
                                                        <div class="info-value">N/A</div>
                                                    </div>
                                                @endif
                                                <div class="col-6">
                                                    <div class="info-label">{{ __('hrms.employees.lbl_threshold_count') }}</div>
                                                    <div class="info-value">{{ $policy->threshold_count }} Marks</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">{{ __('hrms.employees.lbl_deduction_type') }}</div>
                                                    <div class="info-value text-capitalize">{{ str_replace('_', ' ', $policy->penalty_action) }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">{{ __('hrms.employees.lbl_penalty_value') }}</div>
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
            <div class="tab-pane fade {{ $activeTabName === 'documents' ? 'show active' : '' }}" id="documents-pane" role="tabpanel" aria-labelledby="documents-tab">
                <div class="row">
                    <div class="col-12">
                        <div class="card-custom">
                            <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                <div>
                                    <h5 class="card-custom-title"><i class="feather-file-text text-primary"></i> {{ __('hrms.employees.lbl_doc_registry') }}</h5>
                                    <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_doc_registry_desc') }}</small>
                                </div>
                                <div class="documents-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                                    <div class="documents-search d-flex align-items-center px-3 py-1">
                                        <i class="feather-search text-muted me-2 fs-14"></i>
                                        <input 
                                            type="text" 
                                            id="documentSearchInput" 
                                            class="form-control border-0 bg-transparent p-0 fs-13" 
                                            placeholder="{{ __('hrms.employees.lbl_search_docs') }}" 
                                            autocomplete="off"
                                            style="box-shadow: none; height: 32px;"
                                        >
                                    </div>

                                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="title_asc">
                                            <span>{{ __('hrms.employees.lbl_doc_title_asc') }}</span>
                                            <i class="feather-check ms-3"></i>
                                        </a>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="title_desc">
                                            <span>{{ __('hrms.employees.lbl_doc_title_desc') }}</span>
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_asc">
                                            <span>{{ __('hrms.employees.lbl_expiry_soonest') }}</span>
                                        </a>
                                        <a class="dropdown-item document-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="expiry_desc">
                                            <span>{{ __('hrms.employees.lbl_expiry_latest') }}</span>
                                        </a>
                                    </x-ui.sort-dropdown>

                                    <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                        <div class="document-filter-panel">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        <form id="documentFilterForm" onsubmit="return false;">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="status">
                                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                    <option value="uploaded">{{ __('hrms.employees.lbl_uploaded') }}</option>
                                                    <option value="requested">{{ __('hrms.employees.lbl_pending_upload') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.lbl_expiry_req') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="has_expiry">
                                                    <option value="">{{ __('hrms.employees.tbl_actions') }} - {{ __('hrms.common.filter') }}</option>
                                                    <option value="1">{{ __('hrms.employees.lbl_has_expiry') }}</option>
                                                    <option value="0">{{ __('hrms.employees.lbl_no_expiry') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="dropdown-divider my-3"></div>
                                            <div class="document-filter-footer d-flex">
                                                <x-ui.button type="button" id="btnDocumentFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">{{ __('hrms.common.apply') }}</x-ui.button>
                                                <x-ui.button type="button" id="btnDocumentFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">{{ __('hrms.common.reset') }}</x-ui.button>
                                            </div>
                                        </form>
                                        </div>
                                    </x-ui.filter>

                                    <div class="documents-action-group d-flex align-items-center gap-2">
                                        @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                            <x-ui.button variant="light" size="sm" class="document-action-btn" data-bs-toggle="modal" data-bs-target="#requestDocumentModal" icon="feather-git-pull-request">
                                                {{ __('hrms.employees.btn_request_doc') }}
                                            </x-ui.button>
                                        @endif
                                        <x-ui.button variant="primary" size="sm" class="document-action-btn document-action-btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal" icon="feather-upload-cloud">
                                            {{ __('hrms.employees.btn_upload_doc') }}
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
                                                <th class="text-start" style="width: 250px;">{{ __('hrms.employees.tbl_doc_title') }}</th>
                                                <th>{{ __('hrms.employees.tbl_doc_source') }}</th>
                                                <th>{{ __('hrms.employees.tbl_expiry_date') }}</th>
                                                <th>{{ __('hrms.employees.tbl_file') }}</th>
                                                <th>{{ __('hrms.employees.tbl_status') }}</th>
                                                <th style="width: 280px;">{{ __('hrms.employees.tbl_actions') }}</th>
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
                                                            <span class="text-muted fs-11">{{ __('hrms.employees.lbl_no_expiry') }}</span>
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
                                                            <span class="badge bg-soft-warning text-warning">{{ __('hrms.employees.lbl_pending_upload') }}</span>
                                                        @elseif($doc->status === 'uploaded')
                                                            <span class="badge bg-soft-success text-success">{{ __('hrms.employees.lbl_uploaded') }}</span>
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
                                                                            <span class="file-text text-muted" id="file_text_{{ $doc->id }}">{{ __('hrms.employees.lbl_choose_file') }}</span>
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
                                                                    <i class="feather-upload-cloud me-1"></i> {{ __('hrms.employees.btn_upload') }}
                                                                </button>
                                                            </form>
                                                        @else
                                                            <div class="d-flex justify-content-center gap-2">
                                                                @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                                    <form action="{{ route('hrms.employees.documents.destroy', $doc->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this document record?', { title: 'Delete Document Record', variant: 'danger', confirmButtonText: 'Delete' });">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 py-1 px-3" style="border-radius: 6px; font-size: 11px;">
                                                                            <i class="feather-trash-2"></i> {{ __('hrms.employees.btn_remove_record') }}
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
                                                        {{ __('hrms.employees.lbl_no_docs_uploaded') }}
                                                    </td>
                                                </tr>
                                            @else
                                                <tr id="documentNoResultsRow" class="d-none">
                                                    <td colspan="6" class="text-center py-5 text-muted fs-12">
                                                        <i class="feather-search fs-24 d-block mb-2 text-secondary"></i>
                                                        {{ __('hrms.employees.lbl_no_docs_match') }}
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
            <div class="tab-pane fade {{ $activeTabName === 'history' ? 'show active' : '' }}" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                <div class="card-custom">
                    <div class="card-custom-header">
                        <div>
                            <h5 class="card-custom-title"><i class="feather-clock text-primary"></i> {{ __('hrms.employees.lbl_prev_history') }}</h5>
                            <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_prev_history_desc') }}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary py-2 px-3 d-flex align-items-center gap-1" style="border-radius: 6px; font-size: 12px;" data-bs-toggle="modal" data-bs-target="#addHistoryModal">
                            <i class="feather-plus"></i> {{ __('hrms.employees.btn_add_exp') }}
                        </button>
                    </div>
                    <div class="card-body p-4">
                        @if($employee->employmentHistories->isEmpty())
                            <div class="p-5 text-center text-muted">
                                <i class="feather-activity fs-32 d-block mb-3 text-secondary"></i>
                                <div class="fw-bold mb-1">{{ __('hrms.employees.lbl_no_prev_records') }}</div>
                                <div>{{ __('hrms.employees.lbl_no_prev_records_desc') }}</div>
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
                                                    {{ $history->start_date->format('M Y') }} – {{ $history->end_date ? $history->end_date->format('M Y') : __('hrms.employees.lbl_present') }}
                                                </span>
                                                @if($history->job_description)
                                                    <p class="text-muted fs-13 mt-3 mb-0 text-wrap" style="max-width: 100%; white-space: pre-line;">{{ $history->job_description }}</p>
                                                @endif
                                            </div>
                                            @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                <form action="{{ route('hrms.employees.history.destroy', [$employee->id, $history->id]) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this record?', { title: 'Delete Employment History', variant: 'danger', confirmButtonText: 'Delete' });">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 py-1 px-3" style="border-radius: 6px; font-size: 11px;">
                                                        <i class="feather-trash-2"></i> {{ __('hrms.assets.delete') }}
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
            <div class="tab-pane fade {{ $activeTabName === 'assets' ? 'show active' : '' }}" id="assets-pane" role="tabpanel" aria-labelledby="assets-tab">
                @php
                    $categories = \App\Domains\HRMS\Models\AssetCategory::query()->orderBy('name')->get();
                    $assignedAssetCategories = $employee->assets->pluck('category.name')->filter()->unique()->sort()->values();
                    $requestAssetCategories = $employee->assetRequests->pluck('category.name')->filter()->unique()->sort()->values();
                    $requestAssetStatuses = $employee->assetRequests->pluck('status')->filter()->unique()->sort()->values();
                @endphp
                <div class="card-custom">
                    <div class="card-custom-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <h5 class="card-custom-title"><i class="feather-package text-primary"></i> {{ __('hrms.employees.lbl_co_assets') }}</h5>
                            <small class="text-muted d-block mt-1">{{ __('hrms.employees.lbl_co_assets_desc') }}</small>
                        </div>
                        <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                            <div class="documents-search d-flex align-items-center px-3 py-1">
                                <i class="feather-search text-muted me-2 fs-14"></i>
                                <input type="text" id="assignedAssetSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_assets') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                            </div>
                            <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="name_asc">
                                    <span>{{ __('hrms.employees.lbl_asset_name_asc') }}</span>
                                    <i class="feather-check ms-3"></i>
                                </a>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="name_desc">
                                    <span>{{ __('hrms.employees.lbl_asset_name_desc') }}</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_desc">
                                    <span>{{ __('hrms.employees.lbl_assigned_latest') }}</span>
                                </a>
                                <a class="dropdown-item assigned-asset-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="assigned_asc">
                                    <span>{{ __('hrms.employees.lbl_assigned_oldest') }}</span>
                                </a>
                            </x-ui.sort-dropdown>
                            <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                <div class="document-filter-panel">
                                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                                    <form id="assignedAssetFilterForm" onsubmit="return false;">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.lbl_asset_category') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="category">
                                                <option value="">{{ __('hrms.common.all_companies') }} - {{ __('hrms.employees.tbl_category') }}</option>
                                                @foreach($assignedAssetCategories as $categoryName)
                                                    <option value="{{ \Illuminate\Support\Str::lower($categoryName) }}">{{ $categoryName }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.lbl_serial_number') }}</label>
                                            <x-ui.odoo-form-ui type="select" name="serial">
                                                <option value="">{{ __('hrms.employees.tbl_actions') }} - {{ __('hrms.common.filter') }}</option>
                                                <option value="1">{{ __('hrms.employees.lbl_has_serial') }}</option>
                                                <option value="0">{{ __('hrms.employees.lbl_no_serial') }}</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="dropdown-divider my-3"></div>
                                        <div class="document-filter-footer d-flex">
                                            <x-ui.button type="button" id="btnAssignedAssetFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">{{ __('hrms.common.apply') }}</x-ui.button>
                                            <x-ui.button type="button" id="btnAssignedAssetFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">{{ __('hrms.common.reset') }}</x-ui.button>
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
                                        <th class="text-start" style="padding-left: 20px;">{{ __('hrms.employees.tbl_asset_name') }}</th>
                                        <th>{{ __('hrms.employees.tbl_category') }}</th>
                                        <th>Assigned At</th>
                                        <th class="text-end" style="width: 180px; padding-right: 20px;">{{ __('hrms.employees.tbl_actions') }}</th>
                                    </tr>
                                </thead>
                                        @php
                                        $groupedAssets = $employee->assets->groupBy('asset_item_id');
                                    @endphp
                                    @forelse($groupedAssets as $assetItemId => $assets)
                                        @php
                                            $firstAsset = $assets->first();
                                            $itemObj = $firstAsset->item;
                                            $assetCodes = $assets->pluck('asset_code')->join(' ');
                                            $serialNumbers = $assets->pluck('serial_number')->filter()->join(' ');
                                            $assetSearchText = trim(implode(' ', array_filter([
                                                $itemObj?->name,
                                                $firstAsset->brand,
                                                $firstAsset->model_number,
                                                $firstAsset->category?->name,
                                                $assetCodes,
                                                $serialNumbers,
                                            ])));
                                            $encodedAllocatedAssets = base64_encode($assets->toJson());
                                        @endphp
                                        <tr class="assigned-asset-row"
                                            data-name="{{ \Illuminate\Support\Str::lower($itemObj?->name) }}"
                                            data-search="{{ \Illuminate\Support\Str::lower($assetSearchText) }}"
                                            data-category="{{ \Illuminate\Support\Str::lower($itemObj?->category?->name) }}"
                                            data-has-serial="{{ $assets->first(fn($a) => !empty($a->serial_number)) ? '1' : '0' }}"
                                            data-assigned="{{ $firstAsset->allocated_at ? $firstAsset->allocated_at->timestamp : '' }}">
                                            <td class="text-start" style="padding-left: 20px;">
                                                <div class="fw-bold text-dark fs-13">{{ $itemObj?->name }}</div>
                                                <small class="text-muted fs-11">{{ $firstAsset->brand }} {{ $firstAsset->model_number }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary">{{ $itemObj?->category?->name }}</span>
                                            </td>
                                            <td>
                                                <span class="text-secondary fs-13">{{ $firstAsset->allocated_at ? $firstAsset->allocated_at->format('d M, Y') : 'N/A' }}</span>
                                            </td>
                                            <td class="text-end" style="padding-right: 20px;">
                                                <div class="d-flex justify-content-end align-items-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-icon btn-light border" style="border-radius: 8px; width: 32px; height: 32px;" data-bs-toggle="modal" data-bs-target="#viewAssetDetailsModal" data-item-name="{{ $itemObj?->name }}" data-allocated-assets="{{ $encodedAllocatedAssets }}" title="View Details">
                                                        <i class="feather-eye" style="font-size: 14px; color: #475569;"></i>
                                                    </button>
                                                    @if(auth()->user()->hasHrPermission('hr.settings.manage'))
                                                        <button type="button" class="btn btn-sm btn-light border text-uppercase fw-bold px-3 d-inline-flex align-items-center justify-content-center" style="border-color: #cbd5e1; background-color: #ffffff; color: #475569; font-size: 11px; letter-spacing: 0.5px; height: 32px; border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-item-id="{{ $itemObj?->id }}" data-item-name="{{ $itemObj?->name }}" data-allocated-assets="{{ $encodedAllocatedAssets }}">
                                                             {{ __('hrms.employees.btn_return_asset') }}
                                                         </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted fs-12">
                                                <i class="feather-package fs-24 d-block mb-2 text-secondary"></i>
                                                {{ __('hrms.employees.lbl_no_assets_assigned') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    @if($employee->assets->isNotEmpty())
                                        <tr id="assignedAssetNoResultsRow" class="d-none">
                                            <td colspan="4" class="text-center py-5 text-muted fs-12">
                                                <i class="feather-search fs-24 d-block mb-2 text-secondary"></i>
                                                {{ __('hrms.employees.lbl_no_assets_match') }}
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
                                <h5 class="fw-bold mb-0 text-dark" style="font-size: 14px;">{{ __('hrms.employees.lbl_asset_requests_log') }}</h5>
                                <small class="text-muted fs-11">{{ __('hrms.employees.lbl_asset_requests_desc') }}</small>
                            </div>
                            <div class="asset-toolbar d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                                <div class="documents-search d-flex align-items-center px-3 py-1">
                                    <i class="feather-search text-muted me-2 fs-14"></i>
                                    <input type="text" id="assetRequestSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.employees.lbl_search_requests') }}" autocomplete="off" style="box-shadow: none; height: 32px;">
                                </div>
                                <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" data-sort="date_desc">
                                        <span>{{ __('hrms.employees.lbl_assigned_latest') }}</span>
                                        <i class="feather-check ms-3"></i>
                                    </a>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="date_asc">
                                        <span>{{ __('hrms.employees.lbl_assigned_oldest') }}</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="category_asc">
                                        <span>{{ __('hrms.employees.lbl_asset_category') }} (A-Z)</span>
                                    </a>
                                    <a class="dropdown-item asset-request-sort-link d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" data-sort="status_asc">
                                        <span>{{ __('hrms.employees.tbl_status') }} (A-Z)</span>
                                    </a>
                                </x-ui.sort-dropdown>
                                <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                    <div class="document-filter-panel">
                                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                                        <form id="assetRequestFilterForm" onsubmit="return false;">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_requested_category') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="category">
                                                    <option value="">{{ __('hrms.common.all_companies') }} - {{ __('hrms.employees.tbl_category') }}</option>
                                                    @foreach($requestAssetCategories as $categoryName)
                                                        <option value="{{ \Illuminate\Support\Str::lower($categoryName) }}">{{ $categoryName }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.employees.tbl_status') }}</label>
                                                <x-ui.odoo-form-ui type="select" name="status">
                                                    <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                    @foreach($requestAssetStatuses as $statusName)
                                                        <option value="{{ $statusName }}">{{ ucfirst($statusName) }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="dropdown-divider my-3"></div>
                                            <div class="document-filter-footer d-flex">
                                                <x-ui.button type="button" id="btnAssetRequestFilterApply" variant="primary" size="sm" class="flex-grow-1 document-filter-btn">{{ __('hrms.common.apply') }}</x-ui.button>
                                                <x-ui.button type="button" id="btnAssetRequestFilterReset" variant="light" size="sm" class="border flex-grow-1 document-filter-btn">{{ __('hrms.common.reset') }}</x-ui.button>
                                            </div>
                                        </form>
                                    </div>
                                </x-ui.filter>
                                <button type="button" class="btn btn-sm asset-action-btn asset-action-btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#requestAssetModal">
                                    <i class="feather-plus"></i> {{ __('hrms.employees.btn_request_asset') }}
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 text-center asset-requests-table">
                                    <thead class="table-light text-uppercase fs-10" style="letter-spacing: 0.5px;">
                                        <tr>
                                            <th class="text-start" style="padding-left: 20px;">{{ __('hrms.employees.tbl_requested_category') }}</th>
                                            <th>{{ __('hrms.employees.tbl_request_date') }}</th>
                                            <th class="text-start">{{ __('hrms.employees.tbl_reason') }}</th>
                                            <th>{{ __('hrms.employees.tbl_status') }}</th>
                                            <th class="text-start" style="padding-right: 20px;">{{ __('hrms.employees.tbl_admin_notes') }}</th>
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
                                                    <span class="badge bg-soft-primary text-primary mb-1">{{ $req->category->name }}</span>
                                                    @if($req->requestedAsset)
                                                        <div class="fs-11 text-muted">Req Asset: <strong class="text-dark">{{ $req->requestedAsset->name }} ({{ $req->requestedAsset->asset_code }})</strong></div>
                                                    @endif
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
                                                    {{ __('hrms.employees.lbl_no_asset_requests') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                        @if($employee->assetRequests->isNotEmpty())
                                            <tr id="assetRequestNoResultsRow" class="d-none">
                                                <td colspan="5" class="text-center py-4 text-muted fs-11">
                                                    <i class="feather-search fs-20 d-block mb-2 text-secondary"></i>
                                                    {{ __('hrms.employees.lbl_no_asset_requests_match') }}
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
                            <i class="feather-git-pull-request me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_req_doc_title') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.documents.request', $employee->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_doc_name') }}" name="name" :required="true" placeholder="{{ __('hrms.employees.mdl_doc_name_placeholder') }}" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_instructions') }}" name="description" placeholder="{{ __('hrms.employees.mdl_instructions_placeholder') }}" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="radio" label="{{ __('hrms.employees.mdl_requires_expiry') }}" name="has_expiry" :required="true">
                                        <div class="form-check">
                                            <input type="radio" id="has_expiry_yes" name="has_expiry" value="1" class="form-check-input">
                                            <label class="form-check-label fs-13" for="has_expiry_yes">{{ __('hrms.employees.mdl_yes') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="radio" id="has_expiry_no" name="has_expiry" value="0" class="form-check-input" checked>
                                            <label class="form-check-label fs-13" for="has_expiry_no">{{ __('hrms.employees.mdl_no') }}</label>
                                        </div>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_send_request') }}</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
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
                            <i class="feather-upload-cloud me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_upload_doc_title') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.documents.upload', $employee->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_doc_title') }}" name="name" :required="true" placeholder="{{ __('hrms.employees.mdl_doc_title_placeholder') }}" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="file" label="{{ __('hrms.employees.mdl_select_file') }}" name="file" :required="true" placeholder="{{ __('hrms.employees.mdl_select_file_placeholder') }}" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_expiry_date') }}" name="expiry_date" inputType="date" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_upload_file') }}</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
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
                            <i class="feather-clock me-2 text-primary" style="font-size: 16px;"></i>{{ __('hrms.employees.mdl_add_history_title') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('hrms.employees.history.store', $employee->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_company_name') }}" name="company_name" placeholder="{{ __('hrms.employees.mdl_company_name_placeholder') }}" :required="true" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_designation') }}" name="designation" placeholder="{{ __('hrms.employees.mdl_designation_placeholder') }}" :required="true" />
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_start_date') }}" name="start_date" inputType="date" :required="true" />
                                </div>
                                <div class="col-6">
                                    <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_end_date') }}" name="end_date" inputType="date" placeholder="{{ __('hrms.employees.mdl_end_date_placeholder') }}" />
                                </div>
                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_job_desc') }}" name="job_description" rows="3" placeholder="{{ __('hrms.employees.mdl_job_desc_placeholder') }}" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 gap-2">
                            <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_save_exp') }}</button>
                            <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
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
                    <h5 class="modal-title fw-bold" id="addAdhocModalLabel">{{ __('hrms.employees.mdl_add_adhoc_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.adhoc-components.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_adhoc_component') }}" name="salary_component_id" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_component') }}</option>
                                    @foreach($availableAdhocComponents as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->name }} [{{ $ac->code }}]</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_amount_inr') }}" name="amount" inputType="number" step="0.01" placeholder="0.00" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_payroll_month') }}" name="payroll_month" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_payroll_month') }}</option>
                                    @for ($i = -6; $i <= 6; $i++)
                                        @php
                                            $month = now()->addMonths($i);
                                            $val = $month->format('Y-m');
                                            $label = $month->format('F Y');
                                            $selected = ($i === 0) ? 'selected' : '';
                                        @endphp
                                        <option value="{{ $val }}" {{ $selected }}>{{ $label }}</option>
                                    @endfor
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_remarks') }}" name="remarks" rows="3" placeholder="{{ __('hrms.employees.mdl_remarks_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2 justify-content-end">
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_close') }}</button>
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_add_component') }}</button>
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
                    <h5 class="modal-title fw-bold" id="addPenaltyModalLabel">{{ __('hrms.employees.mdl_add_penalty_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.employees.penalties.store', $employee->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_rule_violated') }}" name="rule_type" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_violation') }}</option>
                                    <option value="no_attendance">{{ __('hrms.employees.mdl_violation_no_attendance') }}</option>
                                    <option value="late_arrival">{{ __('hrms.employees.mdl_violation_late_arrival') }}</option>
                                    <option value="under_hours">{{ __('hrms.employees.mdl_violation_under_hours') }}</option>
                                    <option value="missing_logs">{{ __('hrms.employees.mdl_violation_missing_logs') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_violation_date') }}" name="date" inputType="date" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_deduction_value') }}" name="penalty_amount" inputType="number" step="0.01" placeholder="{{ __('hrms.employees.mdl_deduction_placeholder') }}" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_payroll_month') }}" name="payroll_month" select2-selector="default" :required="true">
                                    <option value="">{{ __('hrms.employees.mdl_select_payroll_month') }}</option>
                                    @for ($i = -6; $i <= 6; $i++)
                                        @php
                                            $month = now()->addMonths($i);
                                            $val = $month->format('Y-m');
                                            $label = $month->format('F Y');
                                            $selected = ($i === 0) ? 'selected' : '';
                                        @endphp
                                        <option value="{{ $val }}" {{ $selected }}>{{ $label }}</option>
                                    @endfor
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_remarks') }}" name="remarks" rows="3" placeholder="{{ __('hrms.employees.mdl_remarks_penalty_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2 justify-content-end">
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_close') }}</button>
                        <button type="submit" class="btn btn-danger px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_log_penalty') }}</button>
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
                                    <i class="feather-sliders text-primary me-2"></i>{{ $ltype->name }} {{ __('hrms.employees.mdl_leave_rules_title') }}
                                </h5>
                                <div class="text-muted fs-12 mt-1">
                                    {{ $ltype->code }} · {{ ucfirst($ltype->type) }} · {{ floatval($ltype->quota) }} {{ __('hrms.employees.mdl_yearly_quota') }}
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body bg-light">
                            @if(empty($rulePoints))
                                <div class="text-center py-5 text-muted">
                                    <i class="feather-check-circle d-block fs-32 mb-3 text-success"></i>
                                    <div class="fw-bold text-dark mb-1">{{ __('hrms.employees.mdl_std_rules') }}</div>
                                    <div>{{ __('hrms.employees.mdl_no_custom_rules') }}</div>
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
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.employees.mdl_btn_close') }}</button>
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
                        <i class="feather-corner-up-left me-2 text-primary"></i>{{ __('hrms.employees.mdl_return_asset_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="returnAssetForm" method="POST">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="info-label mb-1">{{ __('hrms.employees.mdl_asset_to_return') }}</label>
                                <input type="text" id="return_asset_name_display" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-12">
                                <label class="info-label mb-2 fw-bold text-dark d-block">Select Serialized Assets to Return</label>
                                <div id="return_assets_checklist" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <!-- Checklist populated via JS -->
                                </div>
                                <small class="text-muted mt-1 d-block">Select the specific physical units being returned.</small>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_return_date') }}" name="returned_at" inputType="date" :required="true" value="{{ date('Y-m-d') }}" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.mdl_return_condition') }}" name="return_condition" :required="true" select2-selector="default">
                                    <option value="good">{{ __('hrms.employees.mdl_condition_good') }}</option>
                                    <option value="new">{{ __('hrms.employees.mdl_condition_new') }}</option>
                                    <option value="fair">{{ __('hrms.employees.mdl_condition_fair') }}</option>
                                    <option value="damaged">{{ __('hrms.employees.mdl_condition_damaged') }}</option>
                                    <option value="scrapped">{{ __('hrms.employees.mdl_condition_scrapped') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_return_notes') }}" name="return_notes" placeholder="{{ __('hrms.employees.mdl_return_notes_placeholder') }}" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_process_return') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW ASSET DETAILS MODAL -->
    <div class="modal fade" id="viewAssetDetailsModal" tabindex="-1" aria-labelledby="viewAssetDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="viewAssetDetailsModalLabel">
                        <i class="feather-info me-2 text-primary"></i>Assigned Asset Units Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="info-label mb-1">Asset Item</label>
                        <input type="text" id="detail_asset_item_name" class="form-control bg-light fw-bold text-dark" readonly>
                    </div>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light text-uppercase fs-11">
                                <tr>
                                    <th class="text-start py-2.5 px-3">Asset Code</th>
                                    <th class="py-2.5">Serial Number</th>
                                    <th class="py-2.5">Assigned Date</th>
                                    <th class="py-2.5">Condition</th>
                                    <th class="py-2.5 px-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody id="detail_assets_table_body" style="font-size: 13px;">
                                <!-- Dynamically populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REQUEST ASSET MODAL FOR PROFILE TAB -->
    <div class="modal fade" id="requestAssetModal" aria-labelledby="requestAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="requestAssetModalLabel">
                        <i class="feather-plus me-2 text-primary"></i>{{ __('hrms.employees.mdl_request_asset_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.assets.requests.store') }}" method="POST" id="requestAssetMultiForm" novalidate>
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.employees.mdl_emp_requesting') }}" name="employee_name_display" value="{{ $employee->display_name }} ({{ $employee->employee_id }})" :readonly="true" />
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="info-label fw-bold text-dark mb-0">Requested Item(s) & Quantities *</label>
                                    <button type="button" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" id="btn-add-req-item-row" style="font-size: 11px;">
                                        <i class="feather-plus me-1"></i>Add Another Item
                                    </button>
                                </div>
                                <div class="table-responsive border rounded bg-white req-item-table-container">
                                    <table class="table table-sm align-middle mb-0" id="req-items-table">
                                        <thead class="table-light text-uppercase fs-11">
                                            <tr>
                                                <th class="py-2 px-3 text-start">Item *</th>
                                                <th class="py-2 text-center" style="width: 85px;">Quantity *</th>
                                                <th class="py-2 text-center px-2" style="width: 45px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="req-items-tbody">
                                            <tr>
                                                <td class="py-2 px-3">
                                                    <select name="items[0][asset_item_id]" class="form-select form-select-sm req-item-select" required>
                                                        <option value="">Select Item</option>
                                                        @foreach(\App\Domains\HRMS\Models\AssetItem::whereHas('category', function($q) use ($employee) { $q->where('company_id', $employee->company_id); })->get() as $item)
                                                            <option value="{{ $item->id }}">{{ $item->name }} (Category: {{ $item->category->name ?? 'N/A' }})</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 text-center">
                                                    <input type="number" name="items[0][quantity]" class="form-control form-control-sm text-center req-qty-input" min="1" value="1" required style="width: 65px; height: 32px; margin: 0 auto; font-weight: 600;">
                                                </td>
                                                <td class="py-2 text-center px-2">
                                                    <button type="button" class="btn btn-sm btn-soft-danger btn-remove-req-item-row" disabled style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-trash-2"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.employees.mdl_reason_req') }}" name="reason" placeholder="{{ __('hrms.employees.mdl_reason_placeholder') }}" :required="true" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 gap-2">
                        <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_submit_request') }}</button>
                        <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.employees.mdl_btn_discard') }}</button>
                    </div>
                </form>
            </div>
        </div>
    <!-- EMPLOYEE PROFILE APPLY LEAVE MODAL (matching Leave Application module) -->
    <div class="modal fade" id="empApplyLeaveModal" aria-labelledby="empApplyLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="empApplyLeaveModalLabel">{{ __('hrms.leave.app.apply_for_leave') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leaves.store') }}" method="POST" enctype="multipart/form-data" id="empApplyLeaveForm">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-12 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.leave_types')" name="leave_type_id" id="emp_leave_type_select" :required="true" class="emp-odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.start_date')" name="start_date" id="emp_start_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.start_session')" name="start_date_type" id="emp_start_date_type" :required="true" class="emp-odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.end_date')" name="end_date" id="emp_end_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.end_session')" name="end_date_type" id="emp_end_date_type" :required="true" class="emp-odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div id="emp_calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0">
                                {{ __('hrms.leave.app.estimated_duration_simple', ['duration' => 0]) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.app.reason_for_leave')" name="reason" :required="true" class="odoo-underline-input" :placeholder="__('hrms.leave.app.reason_placeholder')"></x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.leave.app.upload_attachment')" name="attachment" id="emp_attachment" :required="false" helperText="{{ __('hrms.leave.app.formats_allowed') }}" />
                            <div id="emp_attachment_required_warning" class="text-danger fs-12 mt-1 d-none fw-semibold">
                                <i class="feather-alert-triangle"></i> {{ __('hrms.leave.app.attachment_required_warning') }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.notify_members')" name="notified_contacts[]" id="emp_notified_contacts" :required="false" :multiple="true" class="emp-odoo-select2-custom" :placeholder="__('hrms.leave.app.notify_placeholder')">
                                @foreach ($allEmployees as $emp)
                                    @if ($emp->id !== $employee->id)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark">{{ __('hrms.leave.app.submit_request') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EMPLOYEE PROFILE APPLY ENCASHMENT MODAL (matching Leave Application module) -->
    <div class="modal fade" id="empApplyEncashmentModal" tabindex="-1" aria-labelledby="empApplyEncashmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="empApplyEncashmentModalLabel">{{ __('hrms.leave.encashment_app.apply_for_encashment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('hrms.leaves.encashment.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.encashment_app.select_leave_type')" name="leave_type_id" id="emp_encashment_leave_type_id" :required="true" class="emp-odoo-select2-custom">
                                <option value="">{{ __('hrms.leave.encashment_app.select_leave_type') }}...</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.leave.encashment_app.requested_days')" name="requested_days" id="emp_encashment_requested_days" :required="true" class="odoo-underline-input" step="0.5" min="0.5" placeholder="e.g. 2.5" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.encashment_app.reason')" name="reason" id="emp_encashment_reason" :required="false" class="odoo-underline-input" :placeholder="__('hrms.leave.encashment_app.reason_placeholder')" />
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark"><i class="feather-check me-1"></i> {{ __('hrms.leave.encashment_app.submit_encashment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
        <script>
            var empProfileDataMap = @json($employeeDataMap);

            $(document).ready(function() {
                // Move modals to body root to prevent Bootstrap backdrop overlay issues inside tabs
                $('#addAdhocModal').appendTo('body');
                $('#addPenaltyModal').appendTo('body');
                $('[id^="leaveRulesModal"]').appendTo('body');
                $('#requestDocumentModal').appendTo('body');
                $('#uploadDocumentModal').appendTo('body');
                $('#addHistoryModal').appendTo('body');
                $('#returnAssetModal').appendTo('body');
                $('#viewAssetDetailsModal').appendTo('body');
                $('#requestAssetModal').appendTo('body');
                $('#empApplyLeaveModal').appendTo('body');
                $('#empApplyEncashmentModal').appendTo('body');

                // Initialize Select2 dropdowns inside Apply Leave & Encashment modals
                function initEmpModalSelects() {
                    $('.emp-odoo-select2-custom').each(function() {
                        var $select = $(this);
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }
                        $select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $select.closest('.modal-content'),
                            width: '100%'
                        });
                    });
                }

                initEmpModalSelects();

                // Populate leave types for the employee dynamically from employeeDataMap
                var empId = "{{ $employee->id }}";
                if (empId && empProfileDataMap[empId]) {
                    var $leaveTypeSelect = $('#emp_leave_type_select');
                    $leaveTypeSelect.empty().append('<option value="">{{ __("hrms.leave.app.select_leave_type") }}</option>');
                    var types = empProfileDataMap[empId];
                    types.forEach(function(t) {
                        var text = t.name + ' ({{ __("hrms.leave.app.remaining") }}: ' + t.remaining + ' / ' + t.quota + ' {{ __("hrms.leave.days") }})';
                        var option = $('<option>', {
                            value: t.id,
                            text: text
                        });
                        option.attr('data-rules', JSON.stringify(t.rules));
                        option.attr('data-type', t.type);
                        $leaveTypeSelect.append(option);
                    });
                    $leaveTypeSelect.trigger('change');
                }

                // Leave type change handler — apply attachment / advance rules
                $('#emp_leave_type_select').on('change', function() {
                    var selectedOption = $(this).find('option:selected');
                    var rulesStr = selectedOption.attr('data-rules');
                    if (!rulesStr) return;

                    try {
                        var rules = JSON.parse(rulesStr);
                        var appRules = rules.application || {};

                        // Apply in Advance & Disable invalid dates
                        if (appRules.apply_in_advance) {
                            var advanceDays = parseInt(appRules.advance_days || 3);
                            var minDate = new Date();
                            minDate.setDate(minDate.getDate() + advanceDays);
                            var minDateStr = minDate.getFullYear() + '-' + String(minDate.getMonth() + 1).padStart(2, '0') + '-' + String(minDate.getDate()).padStart(2, '0');
                            $('#emp_start_date').attr('min', minDateStr);
                            $('#emp_end_date').attr('min', minDateStr);
                            if ($('#emp_start_date').val() && $('#emp_start_date').val() < minDateStr) { $('#emp_start_date').val(''); }
                            if ($('#emp_end_date').val() && $('#emp_end_date').val() < minDateStr) { $('#emp_end_date').val(''); }
                        } else {
                            $('#emp_start_date').removeAttr('min');
                            $('#emp_end_date').removeAttr('min');
                        }

                        empCalculateExpectedDuration();
                    } catch (e) {
                        console.error("Error parsing leave rules", e);
                    }
                });

                // Block form submission if dynamic attachment requirement is violated
                $('#empApplyLeaveForm').on('submit', function(e) {
                    var selectedOption = $('#emp_leave_type_select').find('option:selected');
                    var rulesStr = selectedOption.attr('data-rules');
                    if (!rulesStr) return;

                    try {
                        var rules = JSON.parse(rulesStr);
                        var appRules = rules.application || {};
                        if (appRules.require_attachment) {
                            var attachmentDays = parseInt(appRules.attachment_days || 3);
                            var duration = empCalculateExpectedDuration();
                            var hasFile = $('#emp_attachment').val();

                            if (duration >= attachmentDays && !hasFile) {
                                e.preventDefault();
                                alert("{{ __('hrms.leave.app.attachment_required_alert', ['days' => '__days__']) }}".replace('__days__', attachmentDays));
                                return false;
                            }
                        }
                    } catch (err) {
                        console.error("Error running form submit validation", err);
                    }
                });

                // Handle date range select types
                $('#emp_start_date_type, #emp_end_date_type').on('change', function() {
                    empCalculateExpectedDuration();
                });

                $('#emp_start_date, #emp_end_date').on('change', function() {
                    var startDateVal = $('#emp_start_date').val();
                    var endDateVal = $('#emp_end_date').val();
                    if (startDateVal && !endDateVal) {
                        $('#emp_end_date').val(startDateVal);
                    }
                    empCalculateExpectedDuration();
                });

                function empCalculateExpectedDuration() {
                    var startDateStr = $('#emp_start_date').val();
                    var endDateStr = $('#emp_end_date').val();
                    var startType = $('#emp_start_date_type').val() || 'full_day';
                    var endType = $('#emp_end_date_type').val() || 'full_day';

                    if (!startDateStr || !endDateStr) return 0;

                    var start = new Date(startDateStr);
                    var end = new Date(endDateStr);

                    if (end < start) {
                        $('#emp_calculated_duration_display').text("{{ __('hrms.leave.app.date_validation_error') }}");
                        return 0;
                    }

                    var duration = 0;
                    var current = new Date(start);

                    if (start.getTime() === end.getTime()) {
                        if (start.getDay() !== 0) {
                            duration = (startType === 'full_day') ? 1.0 : 0.5;
                        }
                    } else {
                        while (current <= end) {
                            if (current.getDay() !== 0) {
                                var isStart = current.getTime() === start.getTime();
                                var isEnd = current.getTime() === end.getTime();

                                if (isStart) {
                                    duration += (startType === 'full_day') ? 1.0 : 0.5;
                                } else if (isEnd) {
                                    duration += (endType === 'full_day') ? 1.0 : 0.5;
                                } else {
                                    duration += 1.0;
                                }
                            }
                            current.setDate(current.getDate() + 1);
                        }
                    }

                    $('#emp_calculated_duration_display').html("{{ __('hrms.leave.app.estimated_duration', ['duration' => '__duration__']) }}".replace('__duration__', '<strong>' + duration + '</strong>'));

                    // Real-time dynamic attachment warning
                    var selectedOption = $('#emp_leave_type_select').find('option:selected');
                    var rulesStr = selectedOption.attr('data-rules');
                    if (rulesStr) {
                        try {
                            var rules = JSON.parse(rulesStr);
                            var appRules = rules.application || {};
                            if (appRules.require_attachment) {
                                var attachmentDays = parseInt(appRules.attachment_days || 3);
                                if (duration >= attachmentDays) {
                                    $('#emp_attachment_required_warning').removeClass('d-none');
                                    $('#emp_attachment').prop('required', true);
                                } else {
                                    $('#emp_attachment_required_warning').addClass('d-none');
                                    $('#emp_attachment').prop('required', false);
                                }
                            } else {
                                $('#emp_attachment_required_warning').addClass('d-none');
                                $('#emp_attachment').prop('required', false);
                            }
                        } catch (e) {}
                    }

                    return duration;
                }

                // Dynamic Encashment Leave Type Population (filtered by encashment-enabled rules)
                function empUpdateEncashmentLeaveTypes() {
                    var $select = $('#emp_encashment_leave_type_id');
                    $select.empty().append('<option value="">' + "{{ __('hrms.leave.encashment_app.select_leave_type') }}" + '</option>');

                    if (empId && empProfileDataMap[empId]) {
                        var types = empProfileDataMap[empId];
                        types.forEach(function(t) {
                            var encashRules = (t.rules && t.rules.encashment) ? t.rules.encashment : {};
                            var isEnabled = encashRules.enabled === true || encashRules.enabled === '1' || encashRules.enabled === 'true';

                            if (isEnabled) {
                                var text = t.name + ' ({{ __("hrms.leave.app.remaining") }}: ' + t.remaining + ' / ' + t.quota + ' {{ __("hrms.leave.days") }})';
                                var option = $('<option>', { value: t.id, text: text });
                                $select.append(option);
                            }
                        });
                    }
                    $select.trigger('change');
                }

                $('#empApplyEncashmentModal').on('show.bs.modal', function() {
                    empUpdateEncashmentLeaveTypes();
                });

                empUpdateEncashmentLeaveTypes();

                // Theme Select2 initializer for Request Asset Modal
                function initReqModalSelect2(modal) {
                    modal.find('.req-item-select, select[select2-selector="default"]').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            theme: 'bootstrap-5',
                            dropdownParent: modal,
                            placeholder: $(this).attr('placeholder') || "Select Item",
                            width: '100%'
                        });
                    });
                }

                $('#requestAssetModal').on('shown.bs.modal', function() {
                    initReqModalSelect2($(this));
                });

                // Dynamic row management for Multi-Item Asset Request
                let reqItemIndex = 1;

                $('#btn-add-req-item-row').on('click', function() {
                    let tbody = $('#req-items-tbody');
                    let firstSelect = tbody.find('select').first();
                    let firstSelectOptions = '';

                    if (firstSelect.hasClass('select2-hidden-accessible')) {
                        firstSelect.select2('destroy');
                        firstSelectOptions = firstSelect.html();
                        initReqModalSelect2($('#requestAssetModal'));
                    } else {
                        firstSelectOptions = firstSelect.html();
                    }

                    let rowHtml = `
                        <tr>
                            <td class="py-2 px-3">
                                <select name="items[${reqItemIndex}][asset_item_id]" class="form-select form-select-sm req-item-select" required>
                                    ${firstSelectOptions}
                                </select>
                            </td>
                            <td class="py-2 text-center">
                                <input type="number" name="items[${reqItemIndex}][quantity]" class="form-control form-control-sm text-center req-qty-input" min="1" value="1" required style="width: 65px; height: 32px; margin: 0 auto; font-weight: 600;">
                            </td>
                            <td class="py-2 text-center px-2">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-req-item-row" style="width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="feather-trash-2"></i></button>
                            </td>
                        </tr>
                    `;
                    tbody.append(rowHtml);
                    reqItemIndex++;
                    toggleReqItemRemoveButtons();
                    initReqModalSelect2($('#requestAssetModal'));
                });

                $(document).on('click', '.btn-remove-req-item-row', function() {
                    let tbody = $('#req-items-tbody');
                    if (tbody.children('tr').length > 1) {
                        $(this).closest('tr').remove();
                        toggleReqItemRemoveButtons();
                    }
                });

                function toggleReqItemRemoveButtons() {
                    let rows = $('#req-items-tbody tr');
                    if (rows.length <= 1) {
                        rows.find('.btn-remove-req-item-row').prop('disabled', true);
                    } else {
                        rows.find('.btn-remove-req-item-row').prop('disabled', false);
                    }
                }

                // Inline validation for Multi-Item Request Form
                $('#requestAssetMultiForm').on('submit', function(e) {
                    let form = $(this);
                    let invalid = false;

                    form.find('select[name*="[asset_item_id]"]').each(function() {
                        let parent = $(this).parent();
                        if (!$(this).val()) {
                            invalid = true;
                            $(this).addClass('is-invalid');
                            if (parent.find('.invalid-feedback').length === 0) {
                                $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">Please select an item.</div>');
                            }
                        } else {
                            $(this).removeClass('is-invalid');
                            parent.find('.invalid-feedback').remove();
                        }
                    });

                    form.find('input[name*="[quantity]"]').each(function() {
                        let parent = $(this).parent();
                        let val = parseInt($(this).val());
                        if (isNaN(val) || val < 1) {
                            invalid = true;
                            $(this).addClass('is-invalid');
                            if (parent.find('.invalid-feedback').length === 0) {
                                $(this).after('<div class="invalid-feedback fs-11 text-center mt-1">Min quantity is 1.</div>');
                            }
                        } else {
                            $(this).removeClass('is-invalid');
                            parent.find('.invalid-feedback').remove();
                        }
                    });

                    let reasonInput = form.find('textarea[name="reason"]');
                    if (!reasonInput.val() || !reasonInput.val().trim()) {
                        invalid = true;
                        reasonInput.addClass('is-invalid');
                        if (reasonInput.parent().find('.invalid-feedback').length === 0) {
                            reasonInput.after('<div class="invalid-feedback fs-11 text-start mt-1">Reason is required.</div>');
                        }
                    } else {
                        reasonInput.removeClass('is-invalid');
                        reasonInput.parent().find('.invalid-feedback').remove();
                    }

                    if (invalid) {
                        e.preventDefault();
                        return false;
                    }
                });

                $(document).on('change input', '#requestAssetMultiForm select, #requestAssetMultiForm input, #requestAssetMultiForm textarea', function() {
                    if ($(this).val()) {
                        $(this).removeClass('is-invalid');
                        $(this).parent().find('.invalid-feedback').remove();
                    }
                });

                // Handle return modal details binding
                $('#returnAssetModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    var itemId = button.data('item-id');
                    var itemName = button.data('item-name');
                    var rawAssets = button.data('allocated-assets');

                    var modal = $(this);
                    modal.find('form').attr('action', '/hrms/assets/item/' + itemId + '/return');
                    modal.find('#return_asset_name_display').val(itemName);

                    var checklistDiv = modal.find('#return_assets_checklist');
                    checklistDiv.empty();

                    var assets = [];
                    if (rawAssets) {
                        assets = JSON.parse(atob(rawAssets));
                    }

                    if (assets.length === 0) {
                        checklistDiv.html('<span class="text-danger fs-12"><i class="feather-alert-triangle me-1"></i>No active allocations found.</span>');
                    } else {
                        assets.forEach(function(asset) {
                            var checkboxId = 'emp_return_asset_check_' + asset.id;
                            var itemHtml = `
                                <div class="form-check py-1 border-bottom-dashed d-flex align-items-center">
                                    <input class="form-check-input return-allocated-asset-checkbox" type="checkbox" name="allocated_asset_ids[]" value="${asset.id}" id="${checkboxId}" style="cursor: pointer;">
                                    <label class="form-check-label fs-12 ms-2 text-dark mb-0" for="${checkboxId}" style="cursor: pointer;">
                                        <strong>Code:</strong> ${asset.asset_code} | <strong>Serial:</strong> ${asset.serial_number || 'N/A'}
                                    </label>
                                </div>
                            `;
                            checklistDiv.append(itemHtml);
                        });
                    }

                    modal.find('form').off('submit').on('submit', function(e) {
                        var checkedCount = modal.find('.return-allocated-asset-checkbox:checked').length;
                        if (checkedCount === 0) {
                            e.preventDefault();
                            alert('Please select at least one physical asset/serial number to return.');
                        }
                    });
                });

                // Handle view asset details modal binding
                $('#viewAssetDetailsModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget);
                    var itemName = button.data('item-name');
                    var rawAssets = button.data('allocated-assets');

                    var modal = $(this);
                    modal.find('#detail_asset_item_name').val(itemName);

                    var tbody = modal.find('#detail_assets_table_body');
                    tbody.empty();

                    var assets = [];
                    if (rawAssets) {
                        assets = JSON.parse(atob(rawAssets));
                    }

                    if (assets.length === 0) {
                        tbody.append('<tr><td colspan="5" class="py-3 text-muted">No units assigned.</td></tr>');
                    } else {
                        assets.forEach(function(asset) {
                            var dateStr = asset.allocated_at ? new Date(asset.allocated_at).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) : 'N/A';
                            var condBadge = {
                                'new': 'bg-soft-success text-success',
                                'good': 'bg-soft-info text-info',
                                'fair': 'bg-soft-warning text-warning',
                                'damaged': 'bg-soft-danger text-danger',
                                'scrapped': 'bg-soft-secondary text-secondary'
                            };
                            var badgeClass = condBadge[asset.condition] || 'bg-light text-muted';
                            var rowHtml = `
                                <tr>
                                    <td class="text-start py-2 px-3 fw-bold text-dark"><code>${asset.asset_code}</code></td>
                                    <td class="py-2">${asset.serial_number || 'N/A'}</td>
                                    <td class="py-2 text-muted">${dateStr}</td>
                                    <td class="py-2">
                                        <span class="badge ${badgeClass} rounded-pill px-2 py-0.5" style="font-size: 11px;">${asset.condition.charAt(0).toUpperCase() + asset.condition.slice(1)}</span>
                                    </td>
                                    <td class="py-2 px-3 text-muted text-truncate" style="max-width: 150px;" title="${asset.notes || ''}">${asset.notes || '-'}</td>
                                </tr>
                            `;
                            tbody.append(rowHtml);
                        });
                    }
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

        <script>
            // ── Leave Detail Offcanvas ──────────────────────────────────────────
            $(document).on('click', '.open-leave-detail', function () {
                var $row = $(this).closest('tr.leave-app-row');
                var d    = $row.data();

                // Banner
                $('#ld-color-dot').css('background', d.leaveColor);
                $('#ld-leave-type').text(d.leaveType);
                $('#ld-balance-inline').text('Remaining: ' + (d.remaining !== undefined ? d.remaining : '0') + ' / ' + (d.allocated !== undefined ? d.allocated : '0') + ' Days');

                // Status badge
                var statusHtml = '<i class="' + d.statusIcon + ' me-1"></i>' + d.statusLabel;
                $('#ld-status-badge').attr('class', 'badge rounded-pill px-2 py-1 fs-11 flex-shrink-0 ' + d.statusCls)
                                     .html(statusHtml);

                // Applied On
                $('#ld-applied').text(d.applied || '—');

                // Period
                $('#ld-date-range').text(d.dateRange || '—');
                var session = '';
                if (d.startType && d.startType !== 'full day') {
                    var capitalize = function(s) {
                        return s.split(' ').map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1); }).join(' ');
                    };
                    session = capitalize(d.startType);
                    if (d.start !== d.end && d.endType && d.endType !== 'full day') {
                        session += ' → ' + capitalize(d.endType);
                    }
                }
                $('#ld-session-info').text(session);

                // Duration
                $('#ld-duration').text(d.duration + (d.duration == 1 ? ' Day' : ' Days'));

                // Reason
                $('#ld-reason').text(d.reason || '—');

                // Rejection
                if (d.rejection) {
                    $('#ld-rejection-wrap').removeClass('d-none');
                    $('#ld-rejection').text(d.rejection);
                } else {
                    $('#ld-rejection-wrap').addClass('d-none');
                }

                // Attachment
                if (d.attachment) {
                    $('#ld-attach-wrap').removeClass('d-none');
                    $('#ld-attach-link').attr('href', d.attachment);
                } else {
                    $('#ld-attach-wrap').addClass('d-none');
                }

                // Workflow Level
                $('#ld-workflow').text(d.workflow || '—');

                // Notified contacts
                if (d.notifiedNames) {
                    $('#ld-notified-wrap').removeClass('d-none');
                    $('#ld-notified-names').text(d.notifiedNames);
                } else {
                    $('#ld-notified-wrap').addClass('d-none');
                }

                // Status form
                $('#ld-status-form').attr('action', d.updateUrl);
                $('#ld-status-select').val(d.status).trigger('change');

                if (d.status === 'rejected') {
                    $('#ld-rejection-input-wrap').removeClass('d-none');
                    $('#ld-rejection-reason-input').val(d.rejection || '');
                } else {
                    $('#ld-rejection-input-wrap').addClass('d-none');
                    $('#ld-rejection-reason-input').val('');
                }
            });

            // Toggle view between Leave Applications and Leave Encashments in Employee Profile
            $(document).on('click', '#btnToggleLeaveView', function () {
                var isEncashmentHidden = $('#leaveEncashmentsViewContainer').hasClass('d-none');
                if (isEncashmentHidden) {
                    $('#leaveApplicationsViewContainer').addClass('d-none');
                    $('#leaveAppsHeaderTitle').addClass('d-none');
                    $('#leaveAppsToolbar').addClass('d-none');

                    $('#leaveEncashmentsViewContainer').removeClass('d-none');
                    $('#leaveEncashmentsHeaderTitle').removeClass('d-none');
                    $('#leaveEncashmentsToolbar').removeClass('d-none');

                    $('#toggleBtnLabel').html('<i class="feather-calendar me-1"></i> Leave Applications');
                } else {
                    $('#leaveEncashmentsViewContainer').addClass('d-none');
                    $('#leaveEncashmentsHeaderTitle').addClass('d-none');
                    $('#leaveEncashmentsToolbar').addClass('d-none');

                    $('#leaveApplicationsViewContainer').removeClass('d-none');
                    $('#leaveAppsHeaderTitle').removeClass('d-none');
                    $('#leaveAppsToolbar').removeClass('d-none');

                    $('#toggleBtnLabel').html('<i class="feather-dollar-sign me-1"></i> Encashment Details');
                }
            });

            // ── Employee Leave Applications Search, Sort & Filter & Pagination ──
            var empLeaveAppSortMode = 'date_desc';
            var empLeaveAppFilters = { status: '', leave_type_id: '' };
            var empLeaveAppCurrentPage = 1;
            var empLeaveAppPerPage = 10;

            function refreshEmpLeaveAppRows() {
                var query = ($('#empLeaveAppSearchInput').val() || '').toLowerCase().trim();
                var $allRows = $('#leaveAppTable tbody tr.leave-app-row');

                var $matchingRows = $allRows.filter(function () {
                    var $row = $(this);
                    var lType   = ($row.data('leave-type') || '').toString();
                    var lCode   = ($row.data('leave-code') || '').toString();
                    var lReason = ($row.data('reason') || '').toString();
                    var lStatus = ($row.data('status') || '').toString();
                    var typeId  = ($row.data('leave-type-id') || '').toString();

                    var matchesSearch = !query || lType.indexOf(query) !== -1 || lCode.indexOf(query) !== -1 || lReason.indexOf(query) !== -1 || lStatus.indexOf(query) !== -1;
                    var matchesStatus = !empLeaveAppFilters.status || lStatus === empLeaveAppFilters.status;
                    var matchesType   = !empLeaveAppFilters.leave_type_id || typeId === empLeaveAppFilters.leave_type_id;

                    return matchesSearch && matchesStatus && matchesType;
                });

                var totalItems = $matchingRows.length;
                var totalPages = Math.ceil(totalItems / empLeaveAppPerPage) || 1;

                if (empLeaveAppCurrentPage > totalPages) {
                    empLeaveAppCurrentPage = totalPages;
                }
                if (empLeaveAppCurrentPage < 1) {
                    empLeaveAppCurrentPage = 1;
                }

                // Sort visible rows
                var matchingArr = $matchingRows.get();
                matchingArr.sort(function (a, b) {
                    var $a = $(a), $b = $(b);
                    if (empLeaveAppSortMode === 'date_desc') {
                        return ($b.data('created-at') || 0) - ($a.data('created-at') || 0);
                    } else if (empLeaveAppSortMode === 'date_asc') {
                        return ($a.data('created-at') || 0) - ($b.data('created-at') || 0);
                    } else if (empLeaveAppSortMode === 'duration_desc') {
                        return parseFloat($b.data('duration') || 0) - parseFloat($a.data('duration') || 0);
                    } else if (empLeaveAppSortMode === 'duration_asc') {
                        return parseFloat($a.data('duration') || 0) - parseFloat($b.data('duration') || 0);
                    }
                    return 0;
                });

                var startIndex = (empLeaveAppCurrentPage - 1) * empLeaveAppPerPage;
                var endIndex = Math.min(startIndex + empLeaveAppPerPage, totalItems);

                $allRows.addClass('d-none');

                $.each(matchingArr, function (idx, row) {
                    var $r = $(row);
                    $('#leaveAppTable tbody').append($r);
                    if (idx >= startIndex && idx < endIndex) {
                        $r.removeClass('d-none');
                    }
                });

                if (totalItems > empLeaveAppPerPage) {
                    $('#empLeaveAppsPaginationContainer').removeClass('d-none');
                } else {
                    $('#empLeaveAppsPaginationContainer').addClass('d-none');
                }

                if (totalItems === 0) {
                    $('#no_matching_emp_leave_apps_row').removeClass('d-none');
                } else {
                    $('#no_matching_emp_leave_apps_row').addClass('d-none');
                }

                $('#emp_leave_apps_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                $('#emp_leave_apps_showing_end').text(endIndex);
                $('#emp_leave_apps_total_count').text(totalItems);

                var paginationHtml = '';
                paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === 1 ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveAppCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
                paginationHtml += '</li>';

                for (var i = 1; i <= totalPages; i++) {
                    paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === i ? 'active' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                    paginationHtml += '</li>';
                }

                paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === totalPages ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveAppCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
                paginationHtml += '</li>';

                $('#emp_leave_apps_pagination_ul').html(paginationHtml);
            }

            $('#empLeaveAppSearchInput').on('keyup input search', function () {
                empLeaveAppCurrentPage = 1;
                refreshEmpLeaveAppRows();
            });

            $('.emp-leave-app-sort-link').on('click', function (e) {
                e.preventDefault();
                empLeaveAppSortMode = $(this).data('sort') || 'date_desc';
                $('.emp-leave-app-sort-link').removeClass('active').find('.sort-check').addClass('d-none');
                $(this).addClass('active').find('.sort-check').removeClass('d-none');
                empLeaveAppCurrentPage = 1;
                refreshEmpLeaveAppRows();
                $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-sort-dropdown.show').removeClass('show');
            });

            $('#btnEmpLeaveAppFilterApply').on('click', function () {
                empLeaveAppFilters.status = $('#empLeaveAppFilterStatus').val() || '';
                empLeaveAppFilters.leave_type_id = $('#empLeaveAppFilterType').val() || '';
                empLeaveAppCurrentPage = 1;
                refreshEmpLeaveAppRows();
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $('#btnEmpLeaveAppFilterReset').on('click', function () {
                $('#empLeaveAppFilterStatus').val('');
                $('#empLeaveAppFilterType').val('');
                empLeaveAppFilters = { status: '', leave_type_id: '' };
                empLeaveAppCurrentPage = 1;
                refreshEmpLeaveAppRows();
            });

            $(document).on('click', '#emp_leave_apps_pagination_ul .page-link', function (e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page && !$(this).parent().hasClass('disabled')) {
                    empLeaveAppCurrentPage = parseInt(page);
                    refreshEmpLeaveAppRows();
                }
            });

            // ── Employee Leave Encashments Search, Sort & Filter & Pagination ────
            var empLeaveEncSortMode = 'date_desc';
            var empLeaveEncFilters = { status: '', leave_type_id: '' };
            var empLeaveEncCurrentPage = 1;
            var empLeaveEncPerPage = 10;

            function refreshEmpLeaveEncRows() {
                var query = ($('#empLeaveEncSearchInput').val() || '').toLowerCase().trim();
                var $allRows = $('#empLeaveEncashmentTable tbody tr.emp-encash-row');

                var $matchingRows = $allRows.filter(function () {
                    var $row = $(this);
                    var lType   = ($row.data('leave-type') || '').toString();
                    var lReason = ($row.data('reason') || '').toString();
                    var lStatus = ($row.data('status') || '').toString();
                    var typeId  = ($row.data('leave-type-id') || '').toString();

                    var matchesSearch = !query || lType.indexOf(query) !== -1 || lReason.indexOf(query) !== -1 || lStatus.indexOf(query) !== -1;
                    var matchesStatus = !empLeaveEncFilters.status || lStatus === empLeaveEncFilters.status;
                    var matchesType   = !empLeaveEncFilters.leave_type_id || typeId === empLeaveEncFilters.leave_type_id;

                    return matchesSearch && matchesStatus && matchesType;
                });

                var totalItems = $matchingRows.length;
                var totalPages = Math.ceil(totalItems / empLeaveEncPerPage) || 1;

                if (empLeaveEncCurrentPage > totalPages) {
                    empLeaveEncCurrentPage = totalPages;
                }
                if (empLeaveEncCurrentPage < 1) {
                    empLeaveEncCurrentPage = 1;
                }

                // Sort visible rows
                var matchingArr = $matchingRows.get();
                matchingArr.sort(function (a, b) {
                    var $a = $(a), $b = $(b);
                    if (empLeaveEncSortMode === 'date_desc') {
                        return ($b.data('created-at') || 0) - ($a.data('created-at') || 0);
                    } else if (empLeaveEncSortMode === 'date_asc') {
                        return ($a.data('created-at') || 0) - ($b.data('created-at') || 0);
                    } else if (empLeaveEncSortMode === 'days_desc') {
                        return parseFloat($b.data('days') || 0) - parseFloat($a.data('days') || 0);
                    } else if (empLeaveEncSortMode === 'days_asc') {
                        return parseFloat($a.data('days') || 0) - parseFloat($b.data('days') || 0);
                    }
                    return 0;
                });

                var startIndex = (empLeaveEncCurrentPage - 1) * empLeaveEncPerPage;
                var endIndex = Math.min(startIndex + empLeaveEncPerPage, totalItems);

                $allRows.addClass('d-none');

                $.each(matchingArr, function (idx, row) {
                    var $r = $(row);
                    $('#empLeaveEncashmentTable tbody').append($r);
                    if (idx >= startIndex && idx < endIndex) {
                        $r.removeClass('d-none');
                    }
                });

                if (totalItems > empLeaveEncPerPage) {
                    $('#empLeaveEncPaginationContainer').removeClass('d-none');
                } else {
                    $('#empLeaveEncPaginationContainer').addClass('d-none');
                }

                if (totalItems === 0) {
                    $('#no_matching_emp_leave_enc_row').removeClass('d-none');
                } else {
                    $('#no_matching_emp_leave_enc_row').addClass('d-none');
                }

                $('#emp_leave_enc_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                $('#emp_leave_enc_showing_end').text(endIndex);
                $('#emp_leave_enc_total_count').text(totalItems);

                var paginationHtml = '';
                paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === 1 ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveEncCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
                paginationHtml += '</li>';

                for (var i = 1; i <= totalPages; i++) {
                    paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === i ? 'active' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                    paginationHtml += '</li>';
                }

                paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === totalPages ? 'disabled' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveEncCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
                paginationHtml += '</li>';

                $('#emp_leave_enc_pagination_ul').html(paginationHtml);
            }

            $('#empLeaveEncSearchInput').on('keyup input search', function () {
                empLeaveEncCurrentPage = 1;
                refreshEmpLeaveEncRows();
            });

            $('.emp-leave-enc-sort-link').on('click', function (e) {
                e.preventDefault();
                empLeaveEncSortMode = $(this).data('sort') || 'date_desc';
                $('.emp-leave-enc-sort-link').removeClass('active').find('.encash-sort-check').addClass('d-none');
                $(this).addClass('active').find('.encash-sort-check').removeClass('d-none');
                empLeaveEncCurrentPage = 1;
                refreshEmpLeaveEncRows();
                $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-sort-dropdown.show').removeClass('show');
            });

            $('#btnEmpLeaveEncFilterApply').on('click', function () {
                empLeaveEncFilters.status = $('#empLeaveEncFilterStatus').val() || '';
                empLeaveEncFilters.leave_type_id = $('#empLeaveEncFilterType').val() || '';
                empLeaveEncCurrentPage = 1;
                refreshEmpLeaveEncRows();
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            });

            $('#btnEmpLeaveEncFilterReset').on('click', function () {
                $('#empLeaveEncFilterStatus').val('');
                $('#empLeaveEncFilterType').val('');
                empLeaveEncFilters = { status: '', leave_type_id: '' };
                empLeaveEncCurrentPage = 1;
                refreshEmpLeaveEncRows();
            });

            $(document).on('click', '#emp_leave_enc_pagination_ul .page-link', function (e) {
                e.preventDefault();
                var page = $(this).data('page');
                if (page && !$(this).parent().hasClass('disabled')) {
                    empLeaveEncCurrentPage = parseInt(page);
                    refreshEmpLeaveEncRows();
                }
            });

            // Initial trigger on load
            refreshEmpLeaveAppRows();
            refreshEmpLeaveEncRows();

            $(document).on('change', '#ld-status-select', function() {
                if ($(this).val() === 'rejected') {
                    $('#ld-rejection-input-wrap').removeClass('d-none');
                } else {
                    $('#ld-rejection-input-wrap').addClass('d-none');
                }
            });

            // ── Tab Persistence Management ────────────────────────────────────
            // 1. Save active tab on tab click
            $(document).on('shown.bs.tab', '#profileTabs button[data-bs-toggle="tab"]', function (e) {
                var target = $(e.target).attr('data-bs-target');
                if (target) {
                    var tabKey = target.replace('#', '').replace('-pane', '');
                    localStorage.setItem('emp_active_tab_{{ $employee->id }}', target);
                    if (history.replaceState) {
                        history.replaceState(null, null, '#' + tabKey);
                    }
                }
            });

            // 2. Automatically attach hidden tab parameter to any submitted form in the profile
            $(document).on('submit', 'form', function () {
                var $activeBtn = $('#profileTabs button.nav-link.active');
                if ($activeBtn.length) {
                    var targetPane = $activeBtn.attr('data-bs-target');
                    if (targetPane) {
                        var tabName = targetPane.replace('#', '').replace('-pane', '');
                        if (!$(this).find('input[name="tab"]').length && !$(this).find('input[name="active_tab"]').length) {
                            $(this).append('<input type="hidden" name="tab" value="' + tabName + '">');
                        }
                    }
                }
            });

            // 3. Restore saved / hash / query tab on document ready
            (function restoreActiveEmpTab() {
                var urlParams = new URLSearchParams(window.location.search);
                var queryTab = urlParams.get('tab') || urlParams.get('active_tab');
                var hashTab = window.location.hash;
                var savedTab = localStorage.getItem('emp_active_tab_{{ $employee->id }}');

                var targetPane = null;
                if (queryTab) {
                    targetPane = '#' + (queryTab.endsWith('-pane') ? queryTab : queryTab + '-pane');
                } else if (hashTab && hashTab.length > 1) {
                    var rawHash = hashTab.substring(1);
                    targetPane = '#' + (rawHash.endsWith('-pane') ? rawHash : rawHash + '-pane');
                } else if (savedTab) {
                    targetPane = savedTab;
                }

                if (targetPane && $(targetPane).length) {
                    var $tabBtn = $('#profileTabs button[data-bs-target="' + targetPane + '"]');
                    if ($tabBtn.length && !$tabBtn.hasClass('active')) {
                        $('#profileTabs .nav-link').removeClass('active').attr('aria-selected', 'false');
                        $('#profileTabsContent .tab-pane').removeClass('show active');

                        $tabBtn.addClass('active').attr('aria-selected', 'true');
                        $(targetPane).addClass('show active');
                    }
                }
            })();
        </script>
    @endpush
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        #requestAssetModal .odoo-form-label {
            width: 180px !important;
        }
        .req-item-table-container .select2-container--bootstrap-5 .select2-selection {
            min-height: 34px !important;
            height: 34px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            font-size: 12.5px !important;
            border-radius: 0.375rem !important;
        }
        .req-item-table-container .select2-container--bootstrap-5 .select2-selection__rendered {
            line-height: 28px !important;
            font-size: 12.5px !important;
        }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px !important;
            border-color: #dee2e6 !important;
            border-radius: 0.375rem !important;
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: var(--bs-primary) !important;
            border-radius: 0.375rem !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            z-index: 9999 !important;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }
    </style>
@endpush
