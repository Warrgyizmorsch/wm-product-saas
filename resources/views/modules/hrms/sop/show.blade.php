@extends('layouts.duralux')

@section('title', $sop->code . ' - ' . $sop->title . ' | ' . __('hrms.sop.sop_reader'))
@section('page-title', $sop->title)
@section('breadcrumb', __('hrms.sop.breadcrumb_show', ['code' => $sop->code]))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .step-number-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .step-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 15mm 15mm 15mm;
            }
            body {
                background: #fff !important;
                color: #111 !important;
                font-family: 'Segoe UI', Arial, sans-serif !important;
                font-size: 11pt !important;
            }
            .nxl-header, .nxl-navigation, .sidebar, .navbar, .page-header, .page-actions, .erp-actions, .no-print, .btn, .modal, .alert, .col-lg-4 {
                display: none !important;
            }
            .col-lg-8 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
                padding: 0 !important;
            }
            .container-fluid {
                padding: 0 !important;
                max-width: 100% !important;
            }
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                margin-bottom: 20px !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .print-header-table {
                display: table !important;
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .print-header-table td, .print-header-table th {
                border: 1px solid #444 !important;
                padding: 6px 10px;
                font-size: 10pt;
            }
            .print-auth-block {
                display: block !important;
                margin-top: 30px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.sop.index') }}" class="border fw-semibold">
            {{ __('hrms.sop.btn_back_to_hub') }}
        </x-ui.button>
        @if($isHrOrAdmin)
            @if($sop->status === 'published')
                <x-ui.button href="{{ route('hrms.sop.export-audit', $sop->id) }}" variant="light" icon="feather-download" class="border fw-semibold">
                    {{ __('hrms.sop.btn_export_csv') }}
                </x-ui.button>
            @endif
            @if($sop->status === 'draft')
                <x-ui.button variant="success" icon="feather-check-circle" data-bs-toggle="modal" data-bs-target="#publishSopModal" class="fw-bold">
                    {{ __('hrms.sop.btn_publish_sop') }}
                </x-ui.button>
            @endif
            <x-ui.button variant="primary" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#editSopModal" class="fw-bold">
                {{ __('hrms.sop.btn_edit_sop') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- MAIN SOP CONTAINER -->
    <div class="row g-4">
        <!-- LEFT COLUMN: SOP CONTENT & PROCEDURE -->
        <div class="col-lg-8">
            <!-- OFFICIAL PRINT CONTROL HEADER (VISIBLE ON PRINT / PDF ONLY) -->
            <div class="d-none d-print-block mb-4">
                <table class="print-header-table">
                    <tr>
                        <td rowspan="2" class="text-center align-middle" style="width: 25%;">
                            <strong class="fs-14 d-block">{{ config('app.name', 'ENTERPRISE HRMS') }}</strong>
                            <span class="fs-10 text-uppercase tracking-wider text-muted">{{ __('hrms.sop.print_controlled_policy') }}</span>
                        </td>
                        <td class="text-center align-middle" style="width: 50%;">
                            <strong class="fs-15 d-block text-uppercase">{{ $sop->title }}</strong>
                            <span class="fs-11 font-monospace">{{ __('hrms.sop.print_doc_id') }} {{ $sop->code }}</span>
                        </td>
                        <td style="width: 25%;">
                            <div><strong>{{ __('hrms.sop.print_version') }}</strong> {{ $sop->version }}</div>
                            <div><strong>{{ __('hrms.sop.print_status') }}</strong> {{ strtoupper($sop->status) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>{{ __('hrms.sop.print_department') }}</strong> {{ $sop->department->name ?? __('hrms.sop.organization_wide') }} | 
                            <strong>{{ __('hrms.sop.print_category') }}</strong> {{ $sop->category->name ?? __('hrms.sop.cat_general') }}
                        </td>
                        <td>
                            <div><strong>{{ __('hrms.sop.print_effective') }}</strong> {{ $sop->effective_date ? $sop->effective_date->format('Y-m-d') : __('hrms.common.na') }}</div>
                            <div><strong>{{ __('hrms.sop.print_next_review') }}</strong> {{ $sop->next_review_date ? $sop->next_review_date->format('Y-m-d') : __('hrms.common.na') }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SOP HEADER CARD -->
            <x-ui.card class="mb-4 border-0 shadow-sm rounded-3 bg-white">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom no-print">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <x-ui.badge variant="light" class="border font-monospace fs-13 fw-bold px-2.5 py-1">{{ $sop->code }}</x-ui.badge>
                        <x-ui.badge variant="secondary" soft class="fs-12">{{ __('hrms.sop.version_display', ['version' => $sop->version]) }}</x-ui.badge>
                        @if($sop->category)
                            <x-ui.badge variant="primary" soft class="fs-12">
                                <i class="{{ $sop->category->icon ?? 'feather-tag' }} me-1"></i>{{ $sop->category->name }}
                            </x-ui.badge>
                        @endif
                        @if($sop->criticality === 'critical')
                            <x-ui.badge variant="danger" soft class="fs-12">{{ __('hrms.sop.criticality_critical') }}</x-ui.badge>
                        @elseif($sop->criticality === 'high')
                            <x-ui.badge variant="warning" soft class="fs-12">{{ __('hrms.sop.criticality_high') }}</x-ui.badge>
                        @elseif($sop->criticality === 'medium')
                            <x-ui.badge variant="info" soft class="fs-12">{{ __('hrms.sop.criticality_medium') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="light" class="border fs-12">{{ __('hrms.sop.criticality_low') }}</x-ui.badge>
                        @endif
                    </div>
                    <div>
                        @if($sop->status === 'published')
                            <x-ui.badge variant="success" soft class="fs-12 px-3 py-1.5"><i class="feather-check-circle me-1"></i>{{ __('hrms.sop.status_published_active') }}</x-ui.badge>
                        @elseif($sop->status === 'archived')
                            <x-ui.badge variant="light" class="border fs-12 px-3 py-1.5">{{ __('hrms.sop.status_archived') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" soft class="fs-12 px-3 py-1.5"><i class="feather-edit me-1"></i>{{ __('hrms.sop.status_draft') }}</x-ui.badge>
                        @endif
                    </div>
                </div>

                <h3 class="fw-bold text-dark mb-2">{{ $sop->title }}</h3>
                @if($sop->summary)
                    <p class="text-secondary fs-13 mb-3 leading-relaxed">{{ $sop->summary }}</p>
                @endif

                <!-- OBJECTIVE, SCOPE & PREREQUISITES -->
                <div class="row g-3 pt-1">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="p-1.5 rounded bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center">
                                    <i class="feather-target fs-13"></i>
                                </span>
                                <span class="fw-bold fs-12 text-uppercase text-dark">{{ __('hrms.sop.section_purpose_objective') }}</span>
                            </div>
                            <p class="text-secondary fs-13 mb-0 leading-relaxed">{{ $sop->objective ?: __('hrms.sop.default_objective') }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 border bg-light bg-opacity-50 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="p-1.5 rounded bg-info-subtle text-info d-inline-flex align-items-center justify-content-center">
                                    <i class="feather-globe fs-13"></i>
                                </span>
                                <span class="fw-bold fs-12 text-uppercase text-dark">{{ __('hrms.sop.section_scope_applicability') }}</span>
                            </div>
                            <p class="text-secondary fs-13 mb-0 leading-relaxed">{{ $sop->scope ?: ($sop->department ? __('hrms.sop.default_scope_dept', ['dept' => $sop->department->name]) : __('hrms.sop.default_scope_all')) }}</p>
                        </div>
                    </div>
                    @if($sop->prerequisites)
                        <div class="col-12">
                            <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="p-1.5 rounded bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center">
                                        <i class="feather-shield fs-13"></i>
                                    </span>
                                    <span class="fw-bold fs-12 text-uppercase text-dark">{{ __('hrms.sop.section_prerequisites_safety') }}</span>
                                </div>
                                <p class="text-secondary fs-13 mb-0 leading-relaxed">{{ $sop->prerequisites }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                @if($sop->attachment_path)
                    <div class="mt-3 pt-3 border-top no-print">
                        <x-ui.button variant="light" size="sm" icon="feather-paperclip" href="{{ Storage::url($sop->attachment_path) }}" target="_blank" class="border">
                            {{ __('hrms.sop.btn_download_attachment') }}
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            <!-- STEP-BY-STEP PROCEDURE ACCORDION -->
            <x-ui.card class="mb-4 p-2 border-0 shadow-sm rounded-3 bg-white" :title="__('hrms.sop.section_step_by_step')">
                @forelse($sop->sections as $index => $section)
                    <x-ui.card class="border rounded-3 mb-3 overflow-hidden shadow-none">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="step-number-badge">{{ $section->step_number }}</span>
                            <h6 class="fw-bold text-dark mb-0 flex-grow-1">{{ $section->title }}</h6>
                        </div>
                        <div class="text-dark fs-14 leading-relaxed mb-3" style="white-space: pre-line;">{{ $section->content }}</div>

                        @if($section->has_checklist && !empty($section->checklist_items))
                            <div class="bg-light p-3 rounded border">
                                <small class="text-muted fw-bold text-uppercase fs-11 d-block mb-2">
                                    <i class="feather-check-square me-1 text-primary"></i> {{ __('hrms.sop.procedural_checklist_items') }}
                                </small>
                                <ul class="list-unstyled mb-0">
                                    @foreach($section->checklist_items as $item)
                                        <li class="d-flex align-items-center gap-2 py-1 fs-13 text-dark">
                                            <i class="feather-check text-success fs-14"></i>
                                            {{ is_array($item) ? ($item['text'] ?? '') : $item }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </x-ui.card>
                @empty
                    <p class="text-muted text-center py-4">{{ __('hrms.sop.no_steps_added') }}</p>
                @endforelse
            </x-ui.card>

            <!-- EMPLOYEE DIGITAL SIGN-OFF WORKSPACE -->
            @if($myAssignment)
                <x-ui.card class="mb-4 p-2 border shadow-sm rounded-3 bg-white" style="border-top: 4px solid var({{ $myAssignment->status === 'acknowledged' ? '--bs-success' : '--bs-warning' }}) !important;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="feather-shield text-primary"></i> {{ __('hrms.sop.employee_compliance_signoff') }}
                        </h5>
                        @if($myAssignment->status === 'acknowledged')
                            <x-ui.badge variant="success" soft class="fs-13 px-3 py-1.5">
                                <i class="feather-check-circle me-1"></i>{{ __('hrms.sop.signoff_complete') }}
                            </x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" soft class="fs-13 px-3 py-1.5">
                                <i class="feather-clock me-1"></i>{{ __('hrms.sop.action_required') }}
                            </x-ui.badge>
                        @endif
                    </div>

                    @if($sop->status !== 'published')
                        <div class="bg-light p-3 rounded-3 border text-center text-muted">
                            <i class="feather-info fs-24 text-primary d-block mb-1"></i>
                            <h6 class="fw-bold text-dark mb-1 fs-13">{{ __('hrms.sop.prep_notice_title', ['status' => ucfirst(str_replace('_', ' ', $sop->status))]) }}</h6>
                            <small>{{ __('hrms.sop.prep_notice_desc') }}</small>
                        </div>
                    @elseif($myAssignment->status === 'acknowledged')
                        <div class="bg-success-subtle p-3 rounded-3 text-dark">
                            <p class="mb-1 fw-bold text-success">
                                <i class="feather-check-circle me-1"></i> {{ __('hrms.sop.acknowledged_notice', ['version' => $myAssignment->version_assigned]) }}
                            </p>
                            <small class="text-muted d-block">
                                {{ __('hrms.sop.acknowledged_meta', ['date' => $myAssignment->acknowledged_at ? $myAssignment->acknowledged_at->format('M d, Y h:i A') : __('hrms.common.na'), 'ip' => $myAssignment->ip_address ?? __('hrms.sop.ip_logged')]) }}
                            </small>
                        </div>
                    @else
                        <form method="POST" action="{{ route('hrms.sop.acknowledge', $myAssignment->id) }}">
                            @csrf
                            <p class="text-muted fs-13 mb-3">
                                {{ __('hrms.sop.signoff_agreement_text') }}
                            </p>

                            <!-- Verification points display without checkboxes -->
                            @php
                                $allChecklistItems = [];
                                foreach($sop->sections as $sec) {
                                    if(!empty($sec->checklist_items)) {
                                        foreach($sec->checklist_items as $ci) {
                                            $allChecklistItems[] = is_array($ci) ? ($ci['text'] ?? '') : $ci;
                                        }
                                    }
                                }
                            @endphp

                            @if(count($allChecklistItems) > 0)
                                <div class="bg-light p-3 rounded-3 mb-3 border">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-2">{{ __('hrms.sop.required_procedural_verifications') }}</label>
                                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                        @foreach($allChecklistItems as $ciText)
                                            <li class="d-flex align-items-start gap-2 fs-13 text-secondary">
                                                <i class="feather-check-circle text-primary mt-1 flex-shrink-0 fs-13"></i>
                                                <span>{{ $ciText }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mb-4">
                                <x-ui.odoo-form-ui type="checkbox" name="confirm_understanding" value="1" :required="true">
                                    <strong>{{ __('hrms.sop.certify_checkbox_text', ['code' => $sop->code, 'version' => $sop->version]) }}</strong>
                                </x-ui.odoo-form-ui>
                            </div>

                            <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="fw-bold">
                                {{ __('hrms.sop.btn_sign_acknowledge') }}
                            </x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endif

            <!-- REVISION HISTORY TIMELINE -->
            <x-ui.card class="mb-4 p-2 border-0 shadow-sm rounded-3 bg-white" :title="__('hrms.sop.section_version_control')">
                <div class="timeline ps-3 border-start">
                    @forelse($sop->versionHistories as $vh)
                        <div class="mb-3 position-relative ps-3">
                            <span class="badge bg-primary position-absolute" style="left: -22px; top: 2px; width: 12px; height: 12px; border-radius: 50%; padding: 0;"></span>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-bold text-dark fs-13">{{ __('hrms.sop.version_display', ['version' => $vh->version]) }}</span>
                                <x-ui.badge :variant="$vh->change_type === 'major' ? 'danger' : 'light'" :soft="$vh->change_type === 'major'" class="{{ $vh->change_type !== 'major' ? 'border' : '' }}">
                                    {{ __('hrms.sop.badge_version_update', ['type' => ucfirst($vh->change_type)]) }}
                                </x-ui.badge>
                                <small class="text-muted fs-11">{{ $vh->created_at->format('M d, Y') }}</small>
                            </div>
                            <p class="text-muted fs-13 mb-0">{{ $vh->changes_summary }}</p>
                        </div>
                    @empty
                        <p class="text-muted fs-13">{{ __('hrms.sop.initial_version', ['version' => $sop->version]) }}</p>
                    @endforelse
                </div>
            </x-ui.card>

            <!-- OFFICIAL PRINT AUTHORIZATION BLOCK (VISIBLE ON PRINT / PDF ONLY) -->
            <div class="d-none d-print-block print-auth-block mb-4">
                <table class="print-header-table">
                    <tr>
                        <th colspan="2" class="bg-light text-uppercase fw-bold fs-12 py-2">{{ __('hrms.sop.section_authorization_signoff') }}</th>
                    </tr>
                    <tr>
                        <td style="width: 50%; padding: 15px;">
                            <div class="text-muted fs-11 text-uppercase mb-1">{{ __('hrms.sop.print_prepared_by') }}</div>
                            <div class="fw-bold fs-13 mb-3">{{ $sop->creator->name ?? __('hrms.sop.fallback_system_admin') }}</div>
                            <div class="border-bottom border-dark my-2" style="width: 80%;"></div>
                            <div class="fs-11 text-muted">{{ __('hrms.sop.print_author_sig_date') }}</div>
                        </td>
                        <td style="width: 50%; padding: 15px;">
                            <div class="text-muted fs-11 text-uppercase mb-1">{{ __('hrms.sop.print_approved_by') }}</div>
                            <div class="fw-bold fs-13 mb-3">{{ $sop->approver->name ?? ($sop->creator->name ?? __('hrms.sop.fallback_management')) }}</div>
                            <div class="border-bottom border-dark my-2" style="width: 80%;"></div>
                            <div class="fs-11 text-muted">{{ __('hrms.sop.print_authorized_sig_date') }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- RIGHT COLUMN: METADATA & COMPLIANCE ROSTER -->
        <div class="col-lg-4">
            <!-- SOP METADATA CARD -->
            <x-ui.card :title="__('hrms.sop.card_document_metadata')" class="mb-4 border-0 shadow-sm rounded-3 bg-white">
                <table class="table table-sm table-borderless fs-13 mb-0">
                    <tr>
                        <td class="text-muted" style="width: 130px;">{{ __('hrms.sop.lbl_category') }}</td>
                        <td class="fw-semibold text-dark">{{ $sop->category->name ?? __('hrms.sop.cat_general') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">{{ __('hrms.sop.lbl_department') }}</td>
                        <td class="fw-semibold text-dark">{{ $sop->department->name ?? __('hrms.sop.organization_wide') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">{{ __('hrms.sop.lbl_effective_date') }}</td>
                        <td class="fw-semibold text-dark">{{ $sop->effective_date ? $sop->effective_date->format('M d, Y') : __('hrms.common.na') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">{{ __('hrms.sop.lbl_next_review') }}</td>
                        <td class="fw-semibold text-dark">{{ $sop->next_review_date ? $sop->next_review_date->format('M d, Y') : __('hrms.common.na') }}</td>
                    </tr>
                    @php
                        $creatorName = $sop->creator->name ?? __('hrms.sop.fallback_system_admin');
                        $approverName = $sop->approver->name ?? null;
                        $isSamePerson = ($sop->created_by && $sop->approved_by && $sop->created_by === $sop->approved_by) 
                            || ($sop->status === 'published' && (!$sop->approved_by || $sop->created_by === $sop->approved_by));
                    @endphp

                    @if($sop->status === 'draft')
                        <tr>
                            <td class="text-muted">{{ __('hrms.sop.lbl_author_owner') }}</td>
                            <td class="fw-semibold text-dark">{{ $creatorName }}</td>
                        </tr>
                    @elseif($isSamePerson)
                        <tr>
                            <td class="text-muted">{{ __('hrms.sop.lbl_published_by') }}</td>
                            <td class="fw-semibold text-dark">{{ $creatorName }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="text-muted">{{ __('hrms.sop.lbl_created_by') }}</td>
                            <td class="fw-semibold text-dark">{{ $creatorName }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">{{ __('hrms.sop.lbl_approved_by') }}</td>
                            <td class="fw-semibold text-dark">{{ $approverName ?? __('hrms.sop.fallback_admin') }}</td>
                        </tr>
                    @endif
                </table>

                <div class="mt-3 pt-3 border-top d-flex flex-column gap-2">
                    @if($isHrOrAdmin)
                        @if($sop->status === 'draft')
                            <x-ui.button type="button" variant="success" size="sm" icon="feather-check-circle" data-bs-toggle="modal" data-bs-target="#publishSopModal" class="w-100 fw-bold">
                                {{ __('hrms.sop.btn_publish_sop') }}
                            </x-ui.button>
                        @elseif($sop->status === 'published')
                            <form method="POST" action="{{ route('hrms.sop.assign', $sop->id) }}" class="w-100">
                                @csrf
                                <x-ui.button type="submit" variant="outline-primary" size="sm" icon="feather-users" class="w-100 fw-semibold">
                                    {{ __('hrms.sop.btn_sync_dispatch') }}
                                </x-ui.button>
                            </form>
                        @endif
                    @endif
                    <x-ui.button variant="light" size="sm" icon="feather-printer" class="border w-100 fw-semibold text-dark" onclick="window.print()">
                        {{ __('hrms.sop.btn_print_pdf') }}
                    </x-ui.button>
                </div>
            </x-ui.card>

            <!-- STAFF COMPLIANCE TRACKING (ADMIN / HR) -->
            @if($isHrOrAdmin)
                <x-ui.card :title="__('hrms.sop.card_assigned_compliance')" class="mb-4 border-0 shadow-sm rounded-3 bg-white">
                    <x-slot name="headerAction">
                        <x-ui.badge variant="primary" soft>{{ __('hrms.sop.badge_total', ['count' => $assignments->count()]) }}</x-ui.badge>
                    </x-slot>

                    <div style="max-height: 420px; overflow-y: auto; overflow-x: hidden;">
                        <table class="table table-sm table-hover align-middle mb-0 fs-12" style="width: 100%; table-layout: fixed;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-2 text-uppercase fs-11 text-muted" style="width: 48%;">{{ __('hrms.common.employee') }}</th>
                                    <th class="text-uppercase fs-11 text-muted" style="width: 34%;">{{ __('hrms.common.status') }}</th>
                                    <th class="text-end pe-2 text-uppercase fs-11 text-muted text-nowrap" style="width: 18%;">{{ __('hrms.sop.th_remind') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $a)
                                    @php
                                        $empName = $a->employee?->full_name ?? ($a->employee?->first_name ?? __('hrms.common.employee'));
                                        $deptName = $a->employee?->department?->name ?? __('hrms.sop.organization_wide');
                                    @endphp
                                    <tr>
                                        <td class="ps-2 py-2">
                                            <span class="fw-bold text-dark d-block text-truncate fs-12" title="{{ $empName }}">{{ $empName }}</span>
                                            <small class="text-muted d-block text-truncate fs-11" title="{{ $deptName }}">{{ $deptName }}</small>
                                        </td>
                                        <td class="py-2">
                                            @if($a->status === 'acknowledged')
                                                <x-ui.badge variant="success" soft class="fs-10 px-2 py-1"><i class="feather-check me-1"></i>{{ __('hrms.sop.signed') }}</x-ui.badge>
                                                <small class="text-muted d-block fs-10">{{ $a->acknowledged_at ? $a->acknowledged_at->format('M d') : '' }}</small>
                                            @else
                                                <x-ui.badge variant="warning" soft class="fs-10 px-2 py-1"><i class="feather-clock me-1"></i>{{ __('hrms.common.pending') }}</x-ui.badge>
                                                @if($a->due_date && $a->due_date->isPast())
                                                    <x-ui.badge variant="danger" class="fs-10 d-block mt-0.5" style="width: fit-content;">{{ __('hrms.sop.status_overdue') }}</x-ui.badge>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-end pe-2 py-2">
                                            @if($a->status === 'pending')
                                                <form method="POST" action="{{ route('hrms.sop.remind', $a->id) }}" class="d-inline m-0 p-0">
                                                    @csrf
                                                    <x-ui.icon-btn type="submit" icon="feather-bell" variant="warning" size="sm" :title="__('hrms.sop.btn_send_reminder')" />
                                                </form>
                                            @else
                                                <i class="feather-check text-success fs-14"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">
                                            @if($sop->status === 'draft')
                                                <div class="py-2">
                                                    <p class="mb-1 fw-bold text-dark fs-12"><i class="feather-info text-primary me-1"></i>{{ __('hrms.sop.empty_draft_title') }}</p>
                                                    <small class="text-muted">{{ __('hrms.sop.empty_draft_desc') }}</small>
                                                </div>
                                            @else
                                                {{ __('hrms.sop.empty_no_assignments') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT SOP DOCUMENT & BUMP VERSION USING X-UI.MODAL & X-UI.ODOO-FORM-UI -->
<!-- ========================================================================= -->
@if($isHrOrAdmin)
<x-ui.modal id="editSopModal" :title="'<i class=\'feather-edit text-primary me-2\'></i> ' . __('hrms.sop.modal_edit_sop_title', ['code' => $sop->code])" size="xl" :showFooter="false" :centered="true" :scrollable="true">
    <form method="POST" action="{{ route('hrms.sop.update', $sop->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <!-- VERSION CONTROL BUMP -->
            <div class="bg-warning-subtle p-3 rounded-3 mb-4 border border-warning">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="feather-git-branch text-warning fs-14"></i>
                    <h6 class="fw-bold fs-13 text-dark mb-0">{{ __('hrms.sop.section_version_action') }}</h6>
                </div>
                @php
                    $vParts = explode('.', $sop->version ?? '1.0');
                    $nextMinor = ($vParts[0] ?? '1') . '.' . (((int)($vParts[1] ?? 0)) + 1);
                    $nextMajor = (((int)($vParts[0] ?? 1)) + 1) . '.0';
                @endphp
                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" :label="__('hrms.sop.field_version_bump')" name="version_bump">
                            <option value="none">{{ __('hrms.sop.bump_none', ['version' => $sop->version]) }}</option>
                            <option value="minor">{{ __('hrms.sop.bump_minor', ['version' => $nextMinor]) }}</option>
                            <option value="major">{{ __('hrms.sop.bump_major', ['version' => $nextMajor]) }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('hrms.sop.field_changes_summary')" name="changes_summary" :placeholder="__('hrms.sop.placeholder_changes_summary')" />
                    </div>
                </div>
            </div>

            <!-- GENERAL FIELDS -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="step-badge">1</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">{{ __('hrms.sop.section_general_info') }}</h6>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('hrms.sop.field_title')" name="title" value="{{ $sop->title }}" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" :label="__('hrms.sop.field_doc_status')" name="status">
                        <option value="draft" {{ $sop->status === 'draft' ? 'selected' : '' }}>{{ __('hrms.sop.status_draft') }}</option>
                        <option value="published" {{ $sop->status === 'published' ? 'selected' : '' }}>{{ __('hrms.sop.status_published_active') }}</option>
                        <option value="archived" {{ $sop->status === 'archived' ? 'selected' : '' }}>{{ __('hrms.sop.status_archived') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_summary')" name="summary" :value="$sop->summary" rows="2" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_objective')" name="objective" :value="$sop->objective" rows="3" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_scope')" name="scope" :value="$sop->scope" rows="3" />
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_prerequisites')" name="prerequisites" :value="$sop->prerequisites" rows="2" />
                </div>
            </div>

            <!-- PROCEDURE SECTIONS -->
            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                <span class="step-badge">2</span>
                <h6 class="fw-bold text-dark mb-0 fs-13 text-uppercase">{{ __('hrms.sop.section_procedure_steps_checklists') }}</h6>
            </div>
            <div id="editStepsContainer">
                @foreach($sop->sections as $sIdx => $sec)
                    <div class="step-card bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="fw-bold fs-13 text-dark d-flex align-items-center gap-2">
                                <span class="step-badge">{{ $sIdx + 1 }}</span>
                                {{ __('hrms.sop.step_header_format', ['num' => $sIdx + 1]) }}
                            </span>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="input" :label="__('hrms.sop.field_step_title')" name="sections[{{ $sIdx }}][title]" value="{{ $sec->title }}" :required="true" />
                            </div>
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_step_content')" name="sections[{{ $sIdx }}][content]" :value="$sec->content" rows="3" :required="true" />
                            </div>
                            <div class="col-12">
                                @php
                                    $lines = [];
                                    if(!empty($sec->checklist_items)) {
                                        foreach($sec->checklist_items as $ci) {
                                            $lines[] = is_array($ci) ? ($ci['text'] ?? '') : $ci;
                                        }
                                    }
                                @endphp
                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.sop.field_checklist_items')" name="sections[{{ $sIdx }}][checklist_items]" :value="implode(\"\n\", $lines)" rows="2" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.odoo-form-ui>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-3">
            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-check" class="fw-bold">
                {{ __('hrms.common.save_changes') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: CONFIRM PUBLISH SOP -->
<!-- ========================================================================= -->
@if($sop->status === 'draft')
<x-ui.modal id="publishSopModal" :title="'<i class=\'feather-check-circle text-success me-2\'></i> ' . __('hrms.sop.modal_publish_sop_title')" size="md" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.sop.publish', $sop->id) }}">
        @csrf
        <div class="text-center py-2 px-1">
            <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3" style="width: 56px; height: 56px;">
                <i class="feather-check-circle fs-24"></i>
            </div>
            
            <h5 class="fw-bold text-dark mb-1">{{ __('hrms.sop.publish_dialog_title') }}</h5>
            <p class="text-muted fs-12 mb-3">{{ __('hrms.sop.publish_dialog_desc') }}</p>

            <div class="p-3 bg-light rounded-3 border text-start mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-secondary font-monospace">{{ $sop->code }}</span>
                    <span class="fw-bold text-dark fs-13 text-truncate">{{ $sop->title }}</span>
                </div>
                <div class="d-flex flex-column gap-1.5 fs-12 text-secondary">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-users text-primary fs-13"></i>
                        <span>{{ __('hrms.sop.target_audience_label') }} <strong class="text-dark">{{ $sop->target_audience_type === 'all' ? __('hrms.sop.target_audience_all') : ($sop->target_audience_type === 'department' ? __('hrms.sop.target_audience_dept') : __('hrms.sop.target_audience_staff')) }}</strong></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-clock text-warning fs-13"></i>
                        <span>{{ __('hrms.sop.ack_limit_label') }} <strong class="text-dark">{{ __('hrms.sop.days_suffix', ['count' => $sop->acknowledgment_days_limit ?? 7]) }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="alert alert-info text-start d-flex align-items-start gap-2 py-2 px-3 fs-12 mb-0">
                <i class="feather-info text-info fs-14 mt-0.5 flex-shrink-0"></i>
                <div>{{ __('hrms.sop.publish_notification_alert') }}</div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top mt-3">
            <x-ui.button type="button" variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="success" size="sm" icon="feather-check-circle" class="fw-bold">
                {{ __('hrms.sop.btn_publish_dispatch_now') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif

@endif

@endsection
