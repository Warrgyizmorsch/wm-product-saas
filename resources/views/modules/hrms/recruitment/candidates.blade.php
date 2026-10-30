@extends('layouts.duralux')

@section('title', __('hrms.recruitment.candidates_title'))
@section('page-title', __('hrms.recruitment.candidates_page_title'))
@section('breadcrumb', __('hrms.recruitment.candidates_breadcrumb'))

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));

    $recruitmentTabs = [
        [
            'id' => 'tab-requisitions',
            'label' => __('hrms.recruitment.tab_requisitions'),
            'url' => route('hrms.recruitment.requisitions.index'),
            'active' => false,
            'icon' => 'feather-briefcase',
        ],
        [
            'id' => 'tab-candidates',
            'label' => __('hrms.recruitment.tab_candidates'),
            'url' => route('hrms.recruitment.candidates.index'),
            'active' => true,
            'icon' => 'feather-users',
        ],
    ];
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                {{ __('hrms.recruitment.btn_add_candidate') }}
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .cnd-search-form {
        min-width: 260px;
        max-width: 320px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .cnd-search-form:focus-within {
        background-color: #fff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.18rem rgba(0, 0, 0, 0.05);
    }

    /* Recruitment Tables: Prevent horizontal scrollbar & wrap long content to next line */
    .recruitment-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .recruitment-table th,
    .recruitment-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Panel Header with Sub Navigation Tabs -->
        <div class="mb-4 border-bottom pb-2">
            <x-ui.horizontal-tabs id="recruitmentTabs" :tabs="$recruitmentTabs" />
        </div>

        <!-- Search, Sort & Filter Toolbar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.recruitment.candidates_heading') }}</h5>
                <small class="text-muted">{{ __('hrms.recruitment.candidates_subtitle') }}</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Standard Search Bar -->
                <form method="GET" action="{{ route('hrms.recruitment.candidates.index') }}" class="cnd-search-form d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <input type="hidden" name="source" value="{{ $filters['source'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="{{ __('hrms.recruitment.search_placeholder_cnd') }}" 
                        value="{{ $filters['search'] }}"
                        style="box-shadow: none;"
                    >
                    @if($filters['search'])
                        <a href="{{ route('hrms.recruitment.candidates.index', ['status' => $filters['status'], 'source' => $filters['source'], 'sort' => $filters['sort']]) }}" class="text-muted ms-1"><i class="feather-x"></i></a>
                    @endif
                </form>

                <!-- Standard Sort Dropdown Component -->
                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_desc']) }}">
                        <span>{{ __('hrms.recruitment.sort_newest_added') }}</span>
                        @if($filters['sort'] === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_asc']) }}">
                        <span>{{ __('hrms.recruitment.sort_oldest_added') }}</span>
                        @if($filters['sort'] === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}">
                        <span>{{ __('hrms.common.sort_name_asc') }}</span>
                        @if($filters['sort'] === 'name_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}">
                        <span>{{ __('hrms.common.sort_name_desc') }}</span>
                        @if($filters['sort'] === 'name_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'exp_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'exp_desc']) }}">
                        <span>{{ __('hrms.recruitment.sort_highest_exp') }}</span>
                        @if($filters['sort'] === 'exp_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Standard Filter Component -->
                <x-ui.filter :label="__('hrms.common.filter')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                    <form method="GET" action="{{ route('hrms.recruitment.candidates.index') }}">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        
                        <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_candidate_status')" name="status">
                            <option value="">{{ __('hrms.common.all_statuses') }}</option>
                            <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_active_candidate') }}</option>
                            <option value="hired" {{ $filters['status'] === 'hired' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_hired') }}</option>
                            <option value="rejected" {{ $filters['status'] === 'rejected' ? 'selected' : '' }}>{{ __('hrms.recruitment.status_rejected') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_source_channel')" name="source">
                            <option value="">{{ __('hrms.recruitment.all_sources') }}</option>
                            <option value="direct" {{ $filters['source'] === 'direct' ? 'selected' : '' }}>{{ __('hrms.recruitment.source_direct') }}</option>
                            <option value="referral" {{ $filters['source'] === 'referral' ? 'selected' : '' }}>{{ __('hrms.recruitment.source_referral') }}</option>
                            <option value="linkedin" {{ $filters['source'] === 'linkedin' ? 'selected' : '' }}>{{ __('hrms.recruitment.source_linkedin') }}</option>
                            <option value="agency" {{ $filters['source'] === 'agency' ? 'selected' : '' }}>{{ __('hrms.recruitment.source_agency') }}</option>
                        </x-ui.odoo-form-ui>

                        <div class="pt-2 d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="w-100 fw-bold">{{ __('hrms.recruitment.btn_apply_filters') }}</x-ui.button>
                            <x-ui.button variant="light" size="sm" href="{{ route('hrms.recruitment.candidates.index') }}" class="w-100 border">{{ __('hrms.common.reset') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <div class="table-responsive border rounded">
            <table class="table table-hover align-middle mb-0 recruitment-table">
                <thead class="bg-light text-muted fs-12 text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 24%;">{{ __('hrms.recruitment.th_candidate_details') }}</th>
                        <th style="width: 20%;">{{ __('hrms.recruitment.th_contact_info') }}</th>
                        <th style="width: 13%;">{{ __('hrms.recruitment.th_experience') }}</th>
                        <th style="width: 19%;">{{ __('hrms.recruitment.th_applied_position') }}</th>
                        <th class="text-center" style="width: 8%;">{{ __('hrms.recruitment.th_source') }}</th>
                        <th class="text-center" style="width: 8%;">{{ __('hrms.recruitment.th_resume') }}</th>
                        <th class="text-center pe-3" style="width: 8%;">{{ __('hrms.common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($candidates as $candidate)
                        @php
                            $latestApp = $candidate->applications->first();
                            $sourceLabel = match($candidate->source) {
                                'direct' => __('hrms.recruitment.source_direct'),
                                'referral' => __('hrms.recruitment.source_referral'),
                                'linkedin' => __('hrms.recruitment.source_linkedin'),
                                'agency' => __('hrms.recruitment.source_agency'),
                                default => ucfirst($candidate->source),
                            };
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-14">{{ $candidate->full_name }}</div>
                                <div class="text-muted fs-12">{{ $candidate->current_designation ?? __('hrms.common.na') }} @ {{ $candidate->current_company ?? __('hrms.common.na') }}</div>
                                <span class="badge bg-light text-secondary border mt-1 font-monospace fs-11">{{ $candidate->candidate_code }}</span>
                            </td>
                            <td>
                                <div><i class="feather-mail text-muted me-1"></i>{{ $candidate->email }}</div>
                                <small class="text-muted"><i class="feather-phone me-1"></i>{{ $candidate->phone ?? __('hrms.common.na') }}</small>
                            </td>
                            <td>
                                <x-ui.badge variant="info" soft>
                                    <i class="feather-clock me-1"></i>{{ __('hrms.recruitment.yrs_exp_badge', ['count' => $candidate->total_experience_years]) }}
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($latestApp && $latestApp->requisition)
                                    <a href="{{ route('hrms.recruitment.pipeline', ['requisition' => $latestApp->job_requisition_id, 'candidate_id' => $candidate->id]) }}" class="fw-bold text-primary text-decoration-none">
                                        {{ $latestApp->requisition->job_title }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('hrms.recruitment.unassigned') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-ui.badge variant="light">{{ $sourceLabel }}</x-ui.badge>
                            </td>
                            <td class="text-center">
                                @if($candidate->resume_path)
                                    <x-ui.button 
                                        :href="route('hrms.recruitment.candidates.download-resume', $candidate)" 
                                        target="_blank" 
                                        variant="outline-primary" 
                                        size="sm" 
                                        icon="feather-file-text"
                                        class="text-nowrap"
                                    >
                                        {{ __('hrms.recruitment.btn_view_cv') }}
                                    </x-ui.button>
                                @else
                                    <span class="text-muted fs-12">{{ __('hrms.recruitment.no_resume') }}</span>
                                @endif
                            </td>
                            <td class="text-center pe-3">
                                @if($candidate->status === 'hired')
                                    <x-ui.status-badge status="approved" :label="__('hrms.recruitment.status_hired')" />
                                @elseif($candidate->status === 'rejected')
                                    <x-ui.status-badge status="rejected" :label="__('hrms.recruitment.status_rejected')" />
                                @else
                                    <x-ui.status-badge status="active" :label="__('hrms.common.active')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-users fs-36 d-block mb-2 text-secondary"></i>
                                {{ __('hrms.recruitment.no_candidates_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <x-ui.pagination :paginator="$candidates" />
        </div>
    </div>
</div>

<!-- Modal: Add Candidate -->
<x-ui.modal id="addCandidateModal" :title="'<i class=\'feather-user-plus text-primary me-2\'></i>' . __('hrms.recruitment.modal_add_candidate_title')" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.recruitment.candidates.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_target_requisition')" name="job_requisition_id" :required="true">
                    <option value="">{{ __('hrms.recruitment.select_job_opening') }}</option>
                    @foreach($requisitions as $req)
                        <option value="{{ $req->id }}">{{ $req->job_title }} ({{ $req->requisition_code }})</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_candidate_source')" name="source" :required="true">
                    <option value="direct">{{ __('hrms.recruitment.source_direct') }}</option>
                    <option value="referral">{{ __('hrms.recruitment.source_referral') }}</option>
                    <option value="linkedin">{{ __('hrms.recruitment.source_linkedin') }}</option>
                    <option value="agency">{{ __('hrms.recruitment.source_agency') }}</option>
                    <option value="other">{{ __('hrms.recruitment.source_other') }}</option>
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_first_name')" name="first_name" :required="true" :placeholder="__('hrms.recruitment.placeholder_first_name')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_last_name')" name="last_name" :placeholder="__('hrms.recruitment.placeholder_last_name')" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="email" :label="__('hrms.recruitment.field_email')" name="email" :required="true" :placeholder="__('hrms.recruitment.placeholder_email')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="tel" pattern="(?=.*[0-9])[0-9+().\s-]+" :label="__('hrms.recruitment.field_phone')" name="phone" :placeholder="__('hrms.recruitment.placeholder_phone')" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_total_exp')" name="total_experience_years" value="0" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_notice_period')" name="notice_period_days" value="30" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_current_designation')" name="current_designation" :placeholder="__('hrms.recruitment.placeholder_current_designation')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_current_company')" name="current_company" :placeholder="__('hrms.recruitment.placeholder_current_company')" />
            </div>

            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_current_location')" name="current_location" :placeholder="__('hrms.recruitment.placeholder_current_location')" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="file" :label="__('hrms.recruitment.field_attach_resume')" name="resume" :required="true" :helperText="__('hrms.recruitment.helper_resume_formats')" />
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-check">{{ __('hrms.recruitment.btn_save_candidate') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endsection
