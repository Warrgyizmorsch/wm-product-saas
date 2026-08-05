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

        .leave-rules-masonry-grid {
            column-count: 2;
            column-gap: 16px;
        }

        @media (max-width: 767.98px) {
            .leave-rules-masonry-grid {
                column-count: 1;
            }
        }

        .leave-rule-detail-card {
            break-inside: avoid;
            page-break-inside: avoid;
            display: inline-block;
            width: 100%;
            margin-bottom: 16px;
        }

        .leave-rule-detail-section {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
            height: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
                <button class="nav-link {{ $activeTabName === 'wfh' ? 'active' : '' }}" id="wfh-tab" data-bs-toggle="tab" data-bs-target="#wfh-pane" type="button" role="tab" aria-controls="wfh-pane" aria-selected="{{ $activeTabName === 'wfh' ? 'true' : 'false' }}">
                    <i class="feather-home"></i> {{ __('hrms.wfh.title') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ in_array($activeTabName, ['shift_overtime', 'shift-overtime']) ? 'active' : '' }}" id="shift-overtime-tab" data-bs-toggle="tab" data-bs-target="#shift-overtime-pane" type="button" role="tab" aria-controls="shift-overtime-pane" aria-selected="{{ in_array($activeTabName, ['shift_overtime', 'shift-overtime']) ? 'true' : 'false' }}">
                    <i class="feather-clock"></i> {{ __('hrms.employees.tab_shift_overtime') }}
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
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTabName === 'attendance' ? 'active' : '' }}" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-pane" type="button" role="tab" aria-controls="attendance-pane" aria-selected="{{ $activeTabName === 'attendance' ? 'true' : 'false' }}">
                    <i class="feather-clock"></i> Attendance
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- 1. OVERVIEW TAB -->
            @include('modules.hrms.employees.tabs.overview')
            @include('modules.hrms.employees.tabs.compensation')
            @include('modules.hrms.employees.tabs.leaves')
            @include('modules.hrms.employees.tabs.wfh')
            @include('modules.hrms.employees.tabs.shift-overtime')
            @include('modules.hrms.employees.tabs.penalization')
            @include('modules.hrms.employees.tabs.documents')
            @include('modules.hrms.employees.tabs.history')
            @include('modules.hrms.employees.tabs.assets')
            @include('modules.hrms.employees.tabs.attendance')
        </div>
    </div>
    
    @push('scripts')

        <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
        <script>
            var empProfileDataMap = @json($employeeDataMap ?? []);

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
                $('#empApplyWfhModal').appendTo('body');
                $('#wfhCancellationModal').appendTo('body');
                $('#rejectWfhModal').appendTo('body');
                $('#empApplyShiftChangeModal').appendTo('body');
                $('#empApplyOvertimeModal').appendTo('body');
                $('#empApproveOvertimeModal').appendTo('body');
                $('#empRejectOvertimeModal').appendTo('body');

                // Initialize select2 inside modals with dropdownParent to fix Bootstrap focus/typing issue
                $('#empApplyShiftChangeModal select.odoo-select2, #empApplyOvertimeModal select.odoo-select2').each(function() {
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

                // Shift & Overtime Profile Event Listeners & Functions (Select2 compatible)
                $(document).on('change', '#profile_shift_change_type', function() {
                    const val = $(this).val();
                    const $endDateContainer = $('#profile_end_date_container');
                    const $recurringContainer = $('#profile_recurring_days_container');

                    if (val === 'temporary') {
                        $endDateContainer.removeClass('d-none');
                        $recurringContainer.addClass('d-none');
                    } else if (val === 'permanent') {
                        $endDateContainer.addClass('d-none');
                        $recurringContainer.addClass('d-none');
                    } else if (val === 'recurring') {
                        $endDateContainer.addClass('d-none');
                        $recurringContainer.removeClass('d-none');
                    }
                });

                // Auto-fill end date when start date is selected (matching Leave and WFH)
                $('#profile_shift_start_date').on('change', function() {
                    var startDate = $(this).val();
                    if (startDate) {
                        $('#profile_shift_end_date').val(startDate);
                    }
                });

                window.handleEmpShiftDecision = function(action, requestId) {
                    const form = $('<form>', {
                        method: 'POST',
                        action: `{{ url('hrms/shift-change') }}/${requestId}/update-status`
                    });
                    form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                    form.append($('<input>', { type: 'hidden', name: 'action', value: action === 'approve' ? 'approved' : (action === 'reject' ? 'rejected' : 'pending') }));
                    if (action === 'reject') {
                        const reason = prompt('Please enter a rejection reason:');
                        if (reason === null) return;
                        form.append($('<input>', { type: 'hidden', name: 'rejection_reason', value: reason }));
                    }
                    $('body').append(form);
                    form.submit();
                };

                var _pendingEmpOvertimeDecisionId = null;

                window.handleEmpOvertimeDecision = function(action, requestId, requestedHours) {
                    _pendingEmpOvertimeDecisionId = requestId;

                    if (action === 'approve') {
                        $('#empApproveHoursInput').val(requestedHours);
                        var modal = new bootstrap.Modal(document.getElementById('empApproveOvertimeModal'));
                        modal.show();
                    } else if (action === 'reject') {
                        $('#empRejectReasonInput').val('');
                        var modal = new bootstrap.Modal(document.getElementById('empRejectOvertimeModal'));
                        modal.show();
                    } else if (action === 'pending') {
                        submitEmpOvertimeForm('pending', '', '');
                    }
                };

                function submitEmpOvertimeForm(action, approvedHours, reason) {
                    const form = $('<form>', {
                        method: 'POST',
                        action: `{{ url('hrms/overtime') }}/${_pendingEmpOvertimeDecisionId}/update-status`
                    });
                    form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
                    form.append($('<input>', { type: 'hidden', name: 'action', value: action }));
                    form.append($('<input>', { type: 'hidden', name: 'approved_duration_hours', value: approvedHours }));
                    form.append($('<input>', { type: 'hidden', name: 'rejection_reason', value: reason }));
                    $('body').append(form);
                    form.submit();
                }

                $('#confirmEmpApproveBtn').on('click', function() {
                    const hoursVal = parseFloat($('#empApproveHoursInput').val());
                    if (isNaN(hoursVal) || hoursVal <= 0) {
                        alert('Please enter a valid positive number for hours.');
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('empApproveOvertimeModal')).hide();
                    submitEmpOvertimeForm('approved', hoursVal, '');
                });

                $('#confirmEmpRejectBtn').on('click', function() {
                    const reason = $('#empRejectReasonInput').val().trim();
                    bootstrap.Modal.getInstance(document.getElementById('empRejectOvertimeModal')).hide();
                    submitEmpOvertimeForm('rejected', '', reason);
                });

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

                @if($errors->any())
                    setTimeout(function() {
                        var modalEl = document.getElementById('empApplyLeaveModal');
                        if (modalEl) {
                            var bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            bsModal.show();
                        }
                    }, 200);
                @endif

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
                            placeholder: $(this).attr('placeholder') || "{{ __('hrms.employees.mdl_select_item') }}",
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
                                $(this).after('<div class="invalid-feedback fs-11 text-start mt-1">{{ __("hrms.employees.err_item_required") }}</div>');
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
                                $(this).after('<div class="invalid-feedback fs-11 text-center mt-1">{{ __("hrms.employees.err_qty_min") }}</div>');
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
                            reasonInput.after('<div class="invalid-feedback fs-11 text-start mt-1">{{ __("hrms.employees.err_reason_required") }}</div>');
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

            $(document).on('change', '#ld-status-select', function() {
                var selectedVal = $(this).val();
                var form = $('#ld-status-form');
                var baseUpdateUrl = form.data('update-url');
                var approveCancelUrl = form.data('approve-cancel-url');
                var denyCancelUrl = form.data('deny-cancel-url');

                if (selectedVal === 'approve_cancellation') {
                    form.attr('action', approveCancelUrl);
                } else if (selectedVal === 'deny_cancellation') {
                    form.attr('action', denyCancelUrl);
                } else {
                    form.attr('action', baseUpdateUrl);
                }

                if (selectedVal === 'rejected') {
                    $('#ld-rejection-input-wrap').removeClass('d-none');
                } else {
                    $('#ld-rejection-input-wrap').addClass('d-none');
                }
            });

            // Append leaveCancellationModal to body to avoid z-index/backdrop issues
            var leaveCancelEl = document.getElementById('leaveCancellationModal');
            if (leaveCancelEl && leaveCancelEl.parentNode !== document.body) {
                document.body.appendChild(leaveCancelEl);
            }

            window.openLeaveCancellationModal = function(leaveId, actionUrl) {
                var form = document.getElementById('leaveCancellationForm');
                if (form) {
                    form.action = actionUrl;
                }
                document.getElementById('leave_cancellation_reason').value = '';
                var modal = new bootstrap.Modal(document.getElementById('leaveCancellationModal'));
                modal.show();
            };

            window.toggleLeaveCancelReasonText = function(btn) {
                var textEl = btn.previousElementSibling;
                if (textEl.style.display === 'block') {
                    textEl.style.display = '-webkit-box';
                    textEl.style.webkitLineClamp = '2';
                    btn.textContent = 'See more';
                } else {
                    textEl.style.display = 'block';
                    textEl.style.webkitLineClamp = 'none';
                    btn.textContent = 'See less';
                }
            };

            // Toggle view between Shift Applications and Overtime Applications in Employee Profile
            $(document).on('click', '#btnToggleShiftOvertimeView', function () {
                var isOvertimeHidden = $('#overtimeApplicationsViewContainer').hasClass('d-none');
                if (isOvertimeHidden) {
                    $('#shiftApplicationsViewContainer').addClass('d-none');
                    $('#shiftAppsHeaderTitle').addClass('d-none');
                    $('#shiftAppsToolbar').addClass('d-none');

                    $('#overtimeApplicationsViewContainer').removeClass('d-none');
                    $('#overtimeAppsHeaderTitle').removeClass('d-none');
                    $('#overtimeAppsToolbar').removeClass('d-none');

                    $('#toggleShiftOvertimeBtnLabel').html('<i class="feather-git-pull-request me-1"></i> ' + "{{ __('hrms.shift_change.shift_details') }}");
                } else {
                    $('#overtimeApplicationsViewContainer').addClass('d-none');
                    $('#overtimeAppsHeaderTitle').addClass('d-none');
                    $('#overtimeAppsToolbar').addClass('d-none');

                    $('#shiftApplicationsViewContainer').removeClass('d-none');
                    $('#shiftAppsHeaderTitle').removeClass('d-none');
                    $('#shiftAppsToolbar').removeClass('d-none');

                    $('#toggleShiftOvertimeBtnLabel').html('<i class="feather-clock me-1"></i> ' + "{{ __('hrms.shift_change.overtime_details') }}");
                }
            });

            // ── Dynamic Leave Rules Modal Handler ─────────────────────────────
            const langLeaveRules = {
                yearlyQuota: "{{ __('hrms.employees.mdl_yearly_quota') ?? 'Yearly Quota' }}",
                standardRules: "{{ __('hrms.employees.mdl_std_rules') ?? 'Standard Rules' }}",
                noCustomRules: "{{ __('hrms.employees.mdl_no_custom_rules') ?? 'No custom rules defined.' }}",
                
                applicationRules: "{{ __('hrms.leave_rules.application_rules') }}",
                approvalRules: "{{ __('hrms.leave_rules.approval_rules') }}",
                accrualRules: "{{ __('hrms.leave_rules.accrual_rules') }}",
                yearendPolicy: "{{ __('hrms.leave_rules.yearend_policy') }}",
                encashmentRules: "{{ __('hrms.leave_rules.encashment_rules') }}",
                probationRules: "{{ __('hrms.leave_rules.probation_rules') }}",
                noticeRules: "{{ __('hrms.leave_rules.notice_rules') }}",

                applyAdvance: "{{ __('hrms.leave_rules.apply_advance', ['days' => ':days']) }}",
                applyNoAdvance: "{{ __('hrms.leave_rules.apply_no_advance') }}",
                durationLimit: "{{ __('hrms.leave_rules.duration_limit', ['min' => ':min', 'max' => ':max']) }}",
                requireAttachment: "{{ __('hrms.leave_rules.require_attachment', ['days' => ':days']) }}",
                noAttachment: "{{ __('hrms.leave_rules.no_attachment') }}",

                autoApproved: "{{ __('hrms.leave_rules.auto_approved') }}",
                twoApprovals: "{{ __('hrms.leave_rules.two_approvals', ['first' => ':first', 'second' => ':second']) }}",
                oneApproval: "{{ __('hrms.leave_rules.one_approval', ['first' => ':first']) }}",

                unlimitedQuota: "{{ __('hrms.leave_rules.unlimited_quota') }}",
                quotaAllowance: "{{ __('hrms.leave_rules.quota_allowance', ['value' => ':value', 'unit' => ':unit']) }}",
                earnAttendance: "{{ __('hrms.leave_rules.earn_attendance', ['earn' => ':earn', 'period' => ':period']) }}",
                creditPeriodic: "{{ __('hrms.leave_rules.credit_periodic', ['freq' => ':freq', 'prorate' => ':prorate']) }}",
                creditImmediate: "{{ __('hrms.leave_rules.credit_immediate') }}",
                accumLimit: "{{ __('hrms.leave_rules.accum_limit', ['max' => ':max']) }}",
                noAccumLimit: "{{ __('hrms.leave_rules.no_accum_limit') }}",

                carryForward: "{{ __('hrms.leave_rules.carry_forward') }}",
                carryForwardLimit: "{{ __('hrms.leave_rules.carry_forward_limit', ['max' => ':max']) }}",
                encashYearend: "{{ __('hrms.leave_rules.encash_yearend') }}",
                encashLimit: "{{ __('hrms.leave_rules.encash_limit', ['max' => ':max']) }}",
                lapseYearend: "{{ __('hrms.leave_rules.lapse_yearend') }}",

                encashEnabled: "{{ __('hrms.leave_rules.encash_enabled', ['freq' => ':freq']) }}",
                encashMaxPerRequest: "{{ __('hrms.leave_rules.encash_max_per_request', ['max' => ':max']) }}",
                encashMinBalance: "{{ __('hrms.leave_rules.encash_min_balance', ['min' => ':min']) }}",
                encashDisabled: "{{ __('hrms.leave_rules.encash_disabled') }}",

                probationAllow: "{{ __('hrms.leave_rules.probation_allow') }}",
                probationAfterMonths: "{{ __('hrms.leave_rules.probation_after_months', ['months' => ':months']) }}",
                probationDeny: "{{ __('hrms.leave_rules.probation_deny') }}",

                noticeAllow: "{{ __('hrms.leave_rules.notice_allow') }}",
                noticePermission: "{{ __('hrms.leave_rules.notice_permission') }}",
                noticeDeny: "{{ __('hrms.leave_rules.notice_deny') }}",
                prorated: "{{ __('hrms.leave_rules.prorated') }}",
                
                reporting_manager: "{{ __('hrms.leave.reporting_manager') }}",
                department_head: "{{ __('hrms.leave.department_head') }}",
                hr_manager: "{{ __('hrms.leave.hr_manager') }}",
                ceo: "{{ __('hrms.leave.ceo') }}",
                
                days: "{{ __('hrms.leave.days') }}",
                hours: "{{ __('hrms.leave.hours') }}",
                
                frequency_anytime: "{{ __('hrms.leave.frequency_anytime') }}",
                frequency_monthly: "{{ __('hrms.leave.frequency_monthly') }}",
                frequency_quarterly: "{{ __('hrms.leave.frequency_quarterly') }}",
                frequency_half_yearly: "{{ __('hrms.leave.frequency_half_yearly') }}",
                frequency_yearly: "{{ __('hrms.leave.frequency_yearly') }}"
            };

            $(document).on('click', '.view-emp-leave-rules-btn', function () {
                var name = $(this).attr('data-name') || '';
                var code = $(this).attr('data-code') || '';
                var type = $(this).attr('data-type') || '';
                var quota = $(this).attr('data-quota') || '0';
                var rulesStr = $(this).attr('data-rules');
                var rules = {};
                try {
                    rules = rulesStr ? JSON.parse(rulesStr) : {};
                } catch(e) {
                    rules = {};
                }

                $('#empDynamicLeaveTypeName').text(name);
                var metaParts = [];
                if (code) metaParts.push(code);
                if (type) metaParts.push(type);
                if (quota) metaParts.push(quota + ' ' + (langLeaveRules.yearlyQuota || 'days yearly quota'));
                $('#empDynamicLeaveTypeMeta').text(metaParts.join(' · '));

                var sections = [];
                var humanize = function(val) {
                    return (val || '').toString().replace(/_/g, ' ').toLowerCase();
                };

                var translateRole = function(role) {
                    if (!role) return '';
                    var roleLower = role.toLowerCase();
                    return langLeaveRules[roleLower] || humanize(role);
                };

                var translateFrequency = function(freq) {
                    if (!freq) return '';
                    var freqKey = 'frequency_' + freq.toLowerCase();
                    return langLeaveRules[freqKey] || humanize(freq);
                };

                var translateUnit = function(unit) {
                    if (!unit) return '';
                    var unitLower = unit.toLowerCase();
                    return langLeaveRules[unitLower] || humanize(unit);
                };

                // 1. Application Rules
                if (rules.application) {
                    var app = rules.application;
                    var points = [];
                    if (app.apply_in_advance) {
                        points.push(langLeaveRules.applyAdvance.replace(':days', app.advance_days || 0));
                    } else {
                        points.push(langLeaveRules.applyNoAdvance);
                    }
                    points.push(langLeaveRules.durationLimit.replace(':min', app.min_duration || 1).replace(':max', app.max_duration || 10));
                    if (app.require_attachment) {
                        points.push(langLeaveRules.requireAttachment.replace(':days', app.attachment_days || 0));
                    } else {
                        points.push(langLeaveRules.noAttachment);
                    }
                    sections.push({ title: langLeaveRules.applicationRules, icon: 'feather-file-text', points: points });
                }

                // 2. Approval Rules
                if (rules.approval) {
                    var appr = rules.approval;
                    var points = [];
                    if (appr.workflow_level === 'auto') {
                        points.push(langLeaveRules.autoApproved);
                    } else if (appr.workflow_level === '2_level') {
                        points.push(langLeaveRules.twoApprovals
                            .replace(':first', translateRole(appr.first_approver || 'reporting_manager'))
                            .replace(':second', translateRole(appr.second_approver || 'hr_manager'))
                        );
                    } else {
                        points.push(langLeaveRules.oneApproval.replace(':first', translateRole(appr.first_approver || 'reporting_manager')));
                    }
                    sections.push({ title: langLeaveRules.approvalRules, icon: 'feather-check-square', points: points });
                }

                // 3. Accrual Rules
                if (rules.accrual) {
                    var acc = rules.accrual;
                    var points = [];
                    var unit = translateUnit(acc.calculate_in || 'days');
                    if (acc.quota_type === 'unlimited') {
                        points.push(langLeaveRules.unlimitedQuota);
                    } else {
                        var quotaVal = (acc.quota_value !== undefined ? acc.quota_value : quota);
                        var formattedQuota = parseFloat(quotaVal).toString();
                        points.push(langLeaveRules.quotaAllowance.replace(':value', formattedQuota).replace(':unit', unit));
                    }
                    if (acc.rate === 'attendance') {
                        points.push(langLeaveRules.earnAttendance.replace(':earn', acc.attendance_earn || 1).replace(':period', acc.attendance_period || 20));
                    } else if (acc.rate === 'periodic') {
                        var freq = translateFrequency(acc.frequency || 'monthly');
                        var prorate = acc.prorate ? ' (' + langLeaveRules.prorated + ')' : '';
                        points.push(langLeaveRules.creditPeriodic.replace(':freq', freq.toLowerCase()).replace(':prorate', prorate));
                    } else {
                        points.push(langLeaveRules.creditImmediate);
                    }
                    if (acc.limit_carry) {
                        points.push(langLeaveRules.accumLimit.replace(':max', acc.max_accum || 0));
                    } else {
                        points.push(langLeaveRules.noAccumLimit);
                    }
                    sections.push({ title: langLeaveRules.accrualRules, icon: 'feather-calendar', points: points });
                }

                // 4. Year-End Policy
                if (rules.yearend) {
                    var ye = rules.yearend;
                    var points = [];
                    if (ye.action === 'carry_forward') {
                        points.push(langLeaveRules.carryForward);
                        points.push(langLeaveRules.carryForwardLimit.replace(':max', ye.max_carry || 0));
                    } else if (ye.action === 'encash') {
                        points.push(langLeaveRules.encashYearend);
                        points.push(langLeaveRules.encashLimit.replace(':max', ye.max_encash || 0));
                    } else {
                        points.push(langLeaveRules.lapseYearend);
                    }
                    sections.push({ title: langLeaveRules.yearendPolicy, icon: 'feather-rotate-ccw', points: points });
                }

                // 5. Encashment Rules
                if (rules.encashment) {
                    var enc = rules.encashment;
                    var points = [];
                    if (enc.enabled) {
                        var freq = translateFrequency(enc.frequency || 'anytime');
                        points.push(langLeaveRules.encashEnabled.replace(':freq', freq));
                        points.push(langLeaveRules.encashMaxPerRequest.replace(':max', enc.max_days_per_request || 5));
                        points.push(langLeaveRules.encashMinBalance.replace(':min', enc.min_balance_to_keep || 10));
                    } else {
                        points.push(langLeaveRules.encashDisabled);
                    }
                    sections.push({ title: langLeaveRules.encashmentRules, icon: 'feather-dollar-sign', points: points });
                }

                // 6. Probation Period Rules
                if (rules.probation) {
                    var prob = rules.probation;
                    var points = [];
                    if (prob.rule === 'allow') {
                        points.push(langLeaveRules.probationAllow);
                    } else if (prob.rule === 'allow_after_months') {
                        points.push(langLeaveRules.probationAfterMonths.replace(':months', prob.months || 3));
                    } else {
                        points.push(langLeaveRules.probationDeny);
                    }
                    sections.push({ title: langLeaveRules.probationRules, icon: 'feather-user-check', points: points });
                }

                // 7. Notice Period Rules
                if (rules.notice) {
                    var not = rules.notice;
                    var points = [];
                    if (not.rule === 'allow') {
                        points.push(langLeaveRules.noticeAllow);
                    } else if (not.rule === 'allow_with_permission') {
                        points.push(langLeaveRules.noticePermission);
                    } else {
                        points.push(langLeaveRules.noticeDeny);
                    }
                    sections.push({ title: langLeaveRules.noticeRules, icon: 'feather-alert-triangle', points: points });
                }

                var html = '';
                if (sections.length === 0) {
                    html = '<div class="text-center py-5 text-muted">' +
                           '<i class="feather-check-circle d-block fs-32 mb-3 text-success"></i>' +
                           '<div class="fw-bold text-dark mb-1">' + langLeaveRules.standardRules + '</div>' +
                           '<div>' + langLeaveRules.noCustomRules + '</div>' +
                           '</div>';
                } else {
                    html = '<div class="leave-rules-masonry-grid">';
                    sections.forEach(function(sec) {
                        html += '<div class="leave-rule-detail-card">';
                        html += '<div class="leave-rule-detail-section">';
                        html += '<div class="leave-rule-detail-title"><i class="' + sec.icon + ' text-primary me-2"></i> <span>' + sec.title + '</span></div>';
                        html += '<ul class="leave-rule-points">';
                        sec.points.forEach(function(pt) {
                            html += '<li class="leave-rule-point">' + pt + '</li>';
                        });
                        html += '</ul></div></div>';
                    });
                    html += '</div>';
                }

                $('#empDynamicLeaveRulesBody').html(html);
                var $modal = $('#empLeaveRulesDynamicModal');
                if ($modal.length) {
                    $modal.appendTo('body').modal('show');
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

        <script>
            $(document).ready(function() {
                // Fix table-responsive container clipping dropdowns when opened
                $(document).on('show.bs.dropdown', '.table-responsive .dropdown', function () {
                    $(this).closest('.table-responsive').css('overflow', 'visible');
                });
                $(document).on('hide.bs.dropdown', '.table-responsive .dropdown', function () {
                    $(this).closest('.table-responsive').css('overflow', '');
                });

                // Initialize Select2 dropdowns inside Apply WFH modal
                $('#emp_wfh_notified_contacts').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#empApplyWfhModal .modal-content'),
                    width: '100%'
                });

                // WFH duration calculation
                $('#emp_wfh_start_type, #emp_wfh_end_type').on('change', function() {
                    empCalculateExpectedWfhDuration();
                });

                $('#emp_wfh_start_date, #emp_wfh_end_date').on('change', function() {
                    var startDateVal = $('#emp_wfh_start_date').val();
                    var endDateVal = $('#emp_wfh_end_date').val();
                    if (startDateVal && !endDateVal) {
                        $('#emp_wfh_end_date').val(startDateVal);
                    }
                    empCalculateExpectedWfhDuration();
                });

                function empCalculateExpectedWfhDuration() {
                    var startDateStr = $('#emp_wfh_start_date').val();
                    var endDateStr = $('#emp_wfh_end_date').val();
                    var startType = $('#emp_wfh_start_type').val() || 'full_day';
                    var endType = $('#emp_wfh_end_type').val() || 'full_day';

                    if (!startDateStr || !endDateStr) return 0;

                    var start = new Date(startDateStr);
                    var end = new Date(endDateStr);

                    if (end < start) {
                        $('#emp_wfh_calculated_duration_display').removeClass('alert-info').addClass('alert-danger');
                        $('#emp_wfh_duration_val').text('0.0');
                        $('#emp_wfh_session_flow_val').text('(Invalid Date Range)');
                        return 0;
                    }

                    $('#emp_wfh_calculated_duration_display').removeClass('alert-danger').addClass('alert-info');

                    var duration = 0;
                    var current = new Date(start);

                    if (start.getTime() === end.getTime()) {
                        duration = (startType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        while (current <= end) {
                            var isStart = current.getTime() === start.getTime();
                            var isEnd = current.getTime() === end.getTime();

                            if (isStart) {
                                duration += (startType === 'full_day') ? 1.0 : 0.5;
                            } else if (isEnd) {
                                duration += (endType === 'full_day') ? 1.0 : 0.5;
                            } else {
                                duration += 1.0;
                            }
                            current.setDate(current.getDate() + 1);
                        }
                    }

                    var flowText = '(' + (startType === 'full_day' ? 'Full Day' : startType === 'first_half' ? 'First Half' : 'Second Half');
                    if (startDateStr !== endDateStr) {
                        flowText += ' → ' + (endType === 'full_day' ? 'Full Day' : endType === 'first_half' ? 'First Half' : 'Second Half');
                    }
                    flowText += ')';

                    $('#emp_wfh_duration_val').text(duration.toFixed(1));
                    $('#emp_wfh_session_flow_val').text(flowText);
                    $('#emp_wfh_duration').val(duration.toFixed(1));

                    return duration;
                }

                // Inline WFH status updates
                window.submitWfhStatusDirect = function(url, action) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    
                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);

                    var actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = action;
                    form.appendChild(actionInput);

                    document.body.appendChild(form);
                    form.submit();
                };

                // Inline Document status updates
                window.submitDocumentStatusDirect = function(url, status) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    
                    var csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = '{{ csrf_token() }}';
                    form.appendChild(csrfInput);

                    var methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PATCH';
                    form.appendChild(methodInput);

                    var statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    statusInput.value = status;
                    form.appendChild(statusInput);

                    document.body.appendChild(form);
                    form.submit();
                };

                window.toggleDocText = function(btn) {
                    var textEl = btn.previousElementSibling;
                    if (textEl.style.display === 'block') {
                        textEl.style.display = '-webkit-box';
                        textEl.style.webkitLineClamp = '2';
                        btn.textContent = 'See more';
                    } else {
                        textEl.style.display = 'block';
                        textEl.style.webkitLineClamp = 'none';
                        btn.textContent = 'See less';
                    }
                };

                window.adjustDocDescToggles = function() {
                    $('.doc-desc-text').each(function () {
                        var el = this;
                        var $el = $(el);
                        var $toggle = $el.siblings('.doc-toggle-text-btn');
                        
                        if (el.style.display === 'block') {
                            $toggle.removeClass('d-none');
                            return;
                        }
                        
                        if (el.scrollHeight > el.clientHeight) {
                            $toggle.removeClass('d-none');
                        } else {
                            $toggle.addClass('d-none');
                        }
                    });
                };

                window.updateInlineFileName = function(input) {
                    var fileName = input.files[0] ? input.files[0].name : 'Choose File';
                    $(input).siblings('div').find('.file-name-label').text(fileName);
                };

                window.toggleInlineUploadForm = function(docId) {
                    $('#inline-upload-container-' + docId).toggleClass('d-none');
                };

                // Run adjustDocDescToggles when documents tab is active on load or tab switch
                if ($('#documents-pane').hasClass('active')) {
                    setTimeout(window.adjustDocDescToggles, 150);
                }

                $(document).on('shown.bs.tab', '#profileTabs button[data-bs-toggle="tab"]', function (e) {
                    if ($(e.target).attr('data-bs-target') === '#documents-pane') {
                        window.adjustDocDescToggles();
                    }
                });

                $(window).on('resize', function() {
                    if ($('#documents-pane').hasClass('active')) {
                        window.adjustDocDescToggles();
                    }
                });

                // Prefill document ID and details when opening upload modal from table rows
                $(document).on('show.bs.modal', '#uploadDocumentModal', function (e) {
                    var button = $(e.relatedTarget);
                    var docId = button.data('document-id');
                    var docName = button.data('document-name');
                    var modal = $(this);
                    
                    if (docId) {
                        modal.find('#upload_doc_modal_document_id').val(docId);
                        modal.find('input[name="name"]').val(docName).prop('readonly', true);
                        modal.find('.modal-title').html('<i class="feather-upload-cloud me-2 text-primary"></i>Upload ' + docName);
                    } else {
                        modal.find('#upload_doc_modal_document_id').val('');
                        modal.find('input[name="name"]').val('').prop('readonly', false);
                        modal.find('.modal-title').html('<i class="feather-upload-cloud me-2 text-primary"></i>' + "{{ __('hrms.employees.mdl_upload_doc_title') }}");
                    }
                });

                window.openWfhRejectModal = function(btn) {
                    var actionUrl = btn.getAttribute('data-action');
                    var form = document.getElementById('rejectWfhForm');
                    if (form && actionUrl) {
                        form.action = actionUrl;
                        var modalEl = document.getElementById('rejectWfhModal');
                        if (modalEl) {
                            var modal = new bootstrap.Modal(modalEl);
                            modal.show();
                        }
                    }
                };

                // ── WFH Client Side Filter / Search / Pagination ─────────────────────
                var empWfhAppCurrentPage = 1;
                var empWfhAppPerPage = 10;
                var empWfhAppSearchQuery = '';
                var empWfhAppStatusFilter = '';
                var empWfhAppSortMode = 'date_desc';

                function refreshEmpWfhRows() {
                    var $allRows = $('#empWfhTable tbody tr.emp-wfh-row');
                    var matchingArr = [];

                    $allRows.each(function () {
                        var $r = $(this);
                        var reqId = $r.data('req-id');
                        if (!reqId) return;

                        var reason = ($r.data('reason') || '').toString().toLowerCase();
                        var dateRange = ($r.data('date-range') || '').toString().toLowerCase();
                        var status = ($r.data('status') || '').toString().toLowerCase();

                        // 1. Search Query filter
                        var matchesSearch = true;
                        if (empWfhAppSearchQuery) {
                            matchesSearch = (reason.indexOf(empWfhAppSearchQuery) !== -1 || dateRange.indexOf(empWfhAppSearchQuery) !== -1);
                        }

                        // 2. Status Filter
                        var matchesStatus = true;
                        if (empWfhAppStatusFilter) {
                            matchesStatus = (status === empWfhAppStatusFilter);
                        }

                        if (matchesSearch && matchesStatus) {
                            matchingArr.push($r);
                        }
                    });

                    var totalItems = matchingArr.length;

                    // 3. Sort logic
                    matchingArr.sort(function (a, b) {
                        var $a = $(a);
                        var $b = $(b);

                        if (empWfhAppSortMode === 'date_desc') {
                            return parseInt($b.data('created-at') || 0) - parseInt($a.data('created-at') || 0);
                        } else if (empWfhAppSortMode === 'date_asc') {
                            return parseInt($a.data('created-at') || 0) - parseInt($b.data('created-at') || 0);
                        } else if (empWfhAppSortMode === 'duration_desc') {
                            return parseFloat($b.data('duration') || 0) - parseFloat($a.data('duration') || 0);
                        } else if (empWfhAppSortMode === 'duration_asc') {
                            return parseFloat($a.data('duration') || 0) - parseFloat($b.data('duration') || 0);
                        }
                        return 0;
                    });

                    var startIndex = (empWfhAppCurrentPage - 1) * empWfhAppPerPage;
                    var endIndex = Math.min(startIndex + empWfhAppPerPage, totalItems);

                    $allRows.addClass('d-none');

                    $.each(matchingArr, function (idx, row) {
                        var $r = $(row);
                        $('#empWfhTable tbody').append($r);
                        if (idx >= startIndex && idx < endIndex) {
                            $r.removeClass('d-none');
                        }
                    });

                    if (totalItems > empWfhAppPerPage) {
                        $('#empWfhAppsPaginationContainer').removeClass('d-none');
                    } else {
                        $('#empWfhAppsPaginationContainer').addClass('d-none');
                    }

                    if (totalItems === 0) {
                        $('#no_matching_emp_wfh_apps_row').removeClass('d-none');
                    } else {
                        $('#no_matching_emp_wfh_apps_row').addClass('d-none');
                    }

                    $('#emp_wfh_apps_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                    $('#emp_wfh_apps_showing_end').text(endIndex);
                    $('#emp_wfh_apps_total_count').text(totalItems);

                    var paginationHtml = '';
                    paginationHtml += '<li class="page-item ' + (empWfhAppCurrentPage === 1 ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empWfhAppCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
                    paginationHtml += '</li>';

                    var totalPages = Math.ceil(totalItems / empWfhAppPerPage);
                    for (var p = 1; p <= totalPages; p++) {
                        paginationHtml += '<li class="page-item ' + (empWfhAppCurrentPage === p ? 'active' : '') + '">';
                        paginationHtml += '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a>';
                        paginationHtml += '</li>';
                    }

                    paginationHtml += '<li class="page-item ' + (empWfhAppCurrentPage === totalPages || totalPages === 0 ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empWfhAppCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
                    paginationHtml += '</li>';

                    $('#emp_wfh_apps_pagination_ul').html(paginationHtml);
                }

                // Handle pagination link clicks
                $(document).on('click', '#emp_wfh_apps_pagination_ul a.page-link', function (e) {
                    e.preventDefault();
                    var page = parseInt($(this).data('page') || 1);
                    var totalPages = Math.ceil($('#empWfhTable tbody tr.emp-wfh-row').length / empWfhAppPerPage);
                    if (page < 1 || page > totalPages) return;
                    empWfhAppCurrentPage = page;
                    refreshEmpWfhRows();
                });

                // Handle search input keyup
                $('#empWfhAppSearch').on('keyup', function () {
                    empWfhAppSearchQuery = $(this).val().toLowerCase().trim();
                    empWfhAppCurrentPage = 1;
                    refreshEmpWfhRows();
                });

                // Apply Filters
                $('#btnEmpWfhAppFilterApply').on('click', function() {
                    var status = $('#empWfhAppFilterForm select[name="status"]').val();
                    empWfhAppStatusFilter = status ? status.toLowerCase() : '';
                    empWfhAppCurrentPage = 1;
                    refreshEmpWfhRows();
                    // Close bootstrap dropdown parent
                    $('#empWfhAppFilterForm').closest('.dropdown-menu').removeClass('show');
                    $('#empWfhAppFilterForm').closest('.dropdown').removeClass('show');
                });

                // Reset Filters
                $('#btnEmpWfhAppFilterReset').on('click', function() {
                    $('#empWfhAppFilterForm')[0].reset();
                    $('#empWfhAppFilterForm select[name="status"]').val('').trigger('change');
                    empWfhAppStatusFilter = '';
                    empWfhAppCurrentPage = 1;
                    refreshEmpWfhRows();
                    $('#empWfhAppFilterForm').closest('.dropdown-menu').removeClass('show');
                    $('#empWfhAppFilterForm').closest('.dropdown').removeClass('show');
                });

                // Handle Sort Link Click
                $(document).on('click', '.emp-wfh-sort-link', function (e) {
                    e.preventDefault();
                    empWfhAppSortMode = $(this).data('sort') || 'date_desc';
                    $('.emp-wfh-sort-link').removeClass('active').find('.wfh-sort-check').addClass('d-none');
                    $(this).addClass('active').find('.wfh-sort-check').removeClass('d-none');
                    refreshEmpWfhRows();
                });

                // Initial load
                refreshEmpWfhRows();
            });

            window.openWfhCancellationModal = function(wfhId, actionUrl) {
                var form = document.getElementById('wfhCancellationForm');
                if (form) {
                    form.action = actionUrl;
                }
                document.getElementById('wfh_cancellation_reason').value = '';
                var modal = new bootstrap.Modal(document.getElementById('wfhCancellationModal'));
                modal.show();
            };

            window.toggleWfhReasonProfileText = function(btn) {
                var textEl = btn.previousElementSibling;
                if (textEl.style.display === 'block') {
                    textEl.style.display = '-webkit-box';
                    btn.textContent = 'See more';
                } else {
                    textEl.style.display = 'block';
                    btn.textContent = 'See less';
                }
            };

            window.toggleWfhCancelReasonProfileText = function(btn) {
                var textEl = btn.previousElementSibling;
                if (textEl.style.display === 'block') {
                    textEl.style.display = '-webkit-box';
                    btn.textContent = 'See more';
                } else {
                    textEl.style.display = 'block';
                    btn.textContent = 'See less';
                }
            };
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
        .req-item-table-container {
            overflow-x: hidden !important;
        }
        .req-item-table-container #req-items-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .req-item-table-container .select2-container {
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            box-sizing: border-box !important;
        }
        .req-item-table-container .select2-container--bootstrap-5 .select2-selection {
            min-height: 34px !important;
            height: 34px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            font-size: 12.5px !important;
            border-radius: 0.375rem !important;
            display: flex !important;
            align-items: center !important;
            overflow: hidden !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .req-item-table-container .select2-container--bootstrap-5 .select2-selection__rendered {
            line-height: 28px !important;
            font-size: 12.5px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
            display: block !important;
            width: 100% !important;
            max-width: calc(100% - 15px) !important;
            padding-right: 15px !important;
            box-sizing: border-box !important;
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
        .select2-container--bootstrap-5 .select2-results__option {
            white-space: normal !important;
            word-break: break-word !important;
            font-size: 12.5px !important;
            padding: 6px 12px !important;
            line-height: 1.4 !important;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        /* High-specificity overrides to force Select2 options and selection text to dark grey, not blue */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option *,
        body .select2-container .select2-results__option,
        body .select2-container .select2-results__option *,
        body .select2-results__option,
        body .select2-results__option *,
        .select2-results__option,
        .select2-results__option * {
            color: #1e293b !important;
        }

        body .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
        }

        /* High-specificity overrides for highlighted/hovered items */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted *,
        body .select2-container .select2-results__option--highlighted,
        body .select2-container .select2-results__option--highlighted *,
        body .select2-results__option--highlighted,
        body .select2-results__option--highlighted *,
        .select2-results__option--highlighted,
        .select2-results__option--highlighted * {
            color: #ffffff !important;
            background-color: var(--bs-primary) !important;
        }

        /* Action Status Dropdown Styling */
        .btn-status-dropdown {
            background-color: #7c6f6c !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            height: 36px !important;
            border-radius: 8px !important;
            width: 120px !important;
            border: none !important;
            padding: 0 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .btn-status-dropdown:hover,
        .btn-status-dropdown:focus,
        .btn-status-dropdown:active {
            background-color: #6a5e5a !important;
            color: #ffffff !important;
        }
        .btn-status-dropdown::after {
            display: inline-block;
            margin-left: 8px;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            color: #ffffff !important;
        }

        .status-dropdown-menu {
            min-width: 120px !important;
            width: 120px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            padding: 6px !important;
            background: #ffffff !important;
        }
        .status-dropdown-menu .dropdown-item {
            text-align: center !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            color: #1e293b !important;
            background: transparent !important;
            transition: all 0.2s ease;
        }
        .status-dropdown-menu .dropdown-item:hover {
            background-color: #f8fafc !important;
            color: #1e293b !important;
        }
        .status-dropdown-menu .dropdown-item.active-status {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }

        @media (min-width: 992px) {
            #shift-overtime-pane .shift-left-col {
                flex: 0 0 30% !important;
                max-width: 30% !important;
                width: 30% !important;
            }
            #shift-overtime-pane .shift-right-col {
                flex: 0 0 70% !important;
                max-width: 70% !important;
                width: 70% !important;
            }
        }

        /* ── Shift & Overtime tables: no scrollbar, all content wraps ── */

        /* Outer containers: fill width, no clipping that would hide dropdowns */
        #shiftApplicationsViewContainer,
        #overtimeApplicationsViewContainer {
            width: 100%;
        }

        /* Tables always fill the container exactly with fixed column widths */
        #shiftApplicationsViewContainer table,
        #overtimeApplicationsViewContainer table {
            width: 100% !important;
            table-layout: fixed !important;
        }

        /* HEADERS: stay on one line, consistent padding */
        #shiftApplicationsViewContainer th,
        #overtimeApplicationsViewContainer th {
            padding: 10px 12px;
            white-space: nowrap;
            vertical-align: middle;
        }

        /* CELLS: wrap long text within fixed column width */
        #shiftApplicationsViewContainer td,
        #overtimeApplicationsViewContainer td {
            padding: 10px 12px;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            vertical-align: middle;
        }

        /* Left indent on first column */
        #shiftApplicationsViewContainer th:first-child,
        #shiftApplicationsViewContainer td:first-child,
        #overtimeApplicationsViewContainer th:first-child,
        #overtimeApplicationsViewContainer td:first-child {
            padding-left: 20px;
        }

        /* Right indent on last column */
        #shiftApplicationsViewContainer th:last-child,
        #shiftApplicationsViewContainer td:last-child,
        #overtimeApplicationsViewContainer th:last-child,
        #overtimeApplicationsViewContainer td:last-child {
            padding-right: 20px;
        }

        /* Badges inside cells: keep badge text on one line */
        #shiftApplicationsViewContainer td .badge,
        #overtimeApplicationsViewContainer td .badge {
            white-space: nowrap;
            display: inline-block;
            max-width: 100%;
        }

        /* ── Assets & Asset Requests tables: wrap long text ── */
        #assignedAssetsTable td,
        #assignedAssetsTable td *,
        #reqAssetsTable td,
        #reqAssetsTable td * {
            white-space: normal !important;
            word-break: break-word !important;
        }
        #assignedAssetsTable td .badge,
        #reqAssetsTable td .badge {
            white-space: nowrap !important;
        }

        /* ── Table Responsive Dropdown Visibility Fix ── */
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }

        /* ── Custom Sort Dropdown Chevrons ── */
        .erp-sort-dropdown .dropdown-item.active:not(:has(i))::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
            margin-left: 12px;
        }
        .erp-sort-dropdown .dropdown-item.active[data-sort*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="oldest"]:not(:has(i))::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }
    </style>
@endpush
{{-- Recompiled: All tab containers perfectly balanced and validated. --}}
