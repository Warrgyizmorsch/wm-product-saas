@extends('layouts.duralux')

@section('title', __('hrms.offboarding_policies.meta_title'))
@section('page-title', __('hrms.offboarding_policies.page_title'))
@section('breadcrumb', __('hrms.offboarding_policies.breadcrumb'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .policy-point-row {
            transition: all 0.15s ease-in-out;
        }
        .policy-point-row:hover {
            background-color: #f8fafc !important;
        }
        .badge-authority {
            font-weight: 600;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-authority.bg-soft-indigo,
        .badge-authority.text-indigo {
            background-color: rgba(99, 102, 241, 0.12) !important;
            color: #4338ca !important;
            border: 1px solid rgba(99, 102, 241, 0.28) !important;
        }
        .badge-authority.bg-soft-purple,
        .badge-authority.text-purple {
            background-color: rgba(124, 58, 237, 0.12) !important;
            color: #6d28d9 !important;
            border: 1px solid rgba(124, 58, 237, 0.28) !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-rotate-ccw" data-bs-toggle="modal" data-bs-target="#resetTemplatesModal" class="border fw-semibold">
            {{ __('hrms.offboarding_policies.btn_reset_defaults') }}
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold text-uppercase shadow-sm">
            {{ __('hrms.offboarding_policies.btn_add_point') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4 shadow-sm border-0">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4 shadow-sm border-0">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- Main ERP Single Panel -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        
        @php
            $currentParams = [
                'search'       => $search,
                'category'     => $selectedCategory,
                'is_mandatory' => $selectedMandatory,
                'status'       => $selectedStatus,
                'company_id'   => $selectedCompanyId,
                'sort_by'      => $sortBy,
                'sort_order'   => $sortOrder,
            ];
        @endphp

        <!-- Header & Filter Toolbar using Common UI Elements -->
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 pb-3 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-1 fs-15">
                    <i class="feather-clipboard text-primary me-2"></i>{{ __('hrms.offboarding_policies.header_title') }}
                </h5>
                <span class="text-muted fs-12">{{ __('hrms.offboarding_policies.header_subtitle') }}</span>
            </div>

            <!-- Standard Toolbar: Search + Sort Dropdown + Filter Dropdown -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Outside Search Box (Standard ERP Style) -->
                <form method="GET" action="{{ route('hrms.offboarding-policies.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('hrms.offboarding_policies.search_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('hrms.offboarding-policies.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="{{ __('hrms.offboarding_policies.clear_search') }}">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Sort Dropdown Component (Common UI) -->
                <x-ui.sort-dropdown :label="__('hrms.common.sort')" offset="0, 5">
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'order', 'sort_order' => 'asc']) }}" :active="$sortBy === 'order' && $sortOrder === 'asc'">
                        <span>{{ __('hrms.offboarding_policies.sort_display_order_asc') }}</span>
                        <i class="feather-chevron-up text-muted fs-12 ms-auto"></i>
                    </x-ui.dropdown-item>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'order', 'sort_order' => 'desc']) }}" :active="$sortBy === 'order' && $sortOrder === 'desc'">
                        <span>{{ __('hrms.offboarding_policies.sort_display_order_desc') }}</span>
                        <i class="feather-chevron-down text-muted fs-12 ms-auto"></i>
                    </x-ui.dropdown-item>
                    <div class="dropdown-divider"></div>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" :active="$sortBy === 'name' && $sortOrder === 'asc'">
                        <span>{{ __('hrms.offboarding_policies.sort_point_name_asc') }}</span>
                    </x-ui.dropdown-item>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" :active="$sortBy === 'name' && $sortOrder === 'desc'">
                        <span>{{ __('hrms.offboarding_policies.sort_point_name_desc') }}</span>
                    </x-ui.dropdown-item>
                    <div class="dropdown-divider"></div>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'category', 'sort_order' => 'asc']) }}" :active="$sortBy === 'category'">
                        <span>{{ __('hrms.offboarding_policies.sort_department_authority') }}</span>
                    </x-ui.dropdown-item>
                    <x-ui.dropdown-item href="{{ request()->fullUrlWithQuery(['sort_by' => 'mandatory', 'sort_order' => 'desc']) }}" :active="$sortBy === 'mandatory'">
                        <span>{{ __('hrms.offboarding_policies.sort_mandatory_first') }}</span>
                    </x-ui.dropdown-item>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown Component (Common UI matching /ui-elements) -->
                <form method="GET" action="{{ route('hrms.offboarding-policies.index') }}" class="d-inline m-0">
                    @foreach(request()->except(['category', 'is_mandatory', 'status', 'company_id', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach

                    <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.offboarding_policies.filter_title') }}</h6>

                        <!-- Department Authority -->
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.offboarding_policies.filter_department_authority') }}</label>
                            <x-ui.odoo-form-ui type="select" name="category">
                                <option value="">{{ __('hrms.offboarding_policies.filter_all_departments') }}</option>
                                @foreach($availableCategories as $key => $name)
                                    <option value="{{ $key }}" @selected($selectedCategory == $key)>{{ $name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Sign-off Requirement -->
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.offboarding_policies.filter_signoff_requirement') }}</label>
                            <x-ui.odoo-form-ui type="select" name="is_mandatory">
                                <option value="">{{ __('hrms.offboarding_policies.filter_all_requirements') }}</option>
                                <option value="1" @selected($selectedMandatory === '1')>{{ __('hrms.offboarding_policies.filter_mandatory_only') }}</option>
                                <option value="0" @selected($selectedMandatory === '0')>{{ __('hrms.offboarding_policies.filter_optional_only') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Item Status -->
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.offboarding_policies.filter_status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('hrms.offboarding_policies.filter_all_statuses') }}</option>
                                <option value="1" @selected($selectedStatus === '1')>{{ __('hrms.offboarding_policies.filter_active_only') }}</option>
                                <option value="0" @selected($selectedStatus === '0')>{{ __('hrms.offboarding_policies.filter_inactive_only') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Company Scope (if multi-company) -->
                        @if($companies->count() > 1)
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.offboarding_policies.filter_company_scope') }}</label>
                                <x-ui.odoo-form-ui type="select" name="company_id">
                                    <option value="">{{ __('hrms.offboarding_policies.filter_all_companies') }}</option>
                                    @foreach($companies as $comp)
                                        <option value="{{ $comp->id }}" @selected($selectedCompanyId == $comp->id)>{{ $comp->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="dropdown-divider my-3"></div>

                        <div class="d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.offboarding_policies.filter_btn_apply') }}</x-ui.button>
                            <x-ui.button href="{{ route('hrms.offboarding-policies.index') }}" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.offboarding_policies.filter_btn_reset') }}</x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>

                @if($hasActiveFilters ?? false)
                    <x-ui.button href="{{ route('hrms.offboarding-policies.index') }}" variant="light" size="sm" class="border text-danger fw-semibold" icon="feather-rotate-ccw" title="{{ __('hrms.offboarding_policies.filter_btn_reset') }}">
                        {{ __('hrms.common.reset') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        <!-- Active Filter Badges Bar -->
        @if($hasActiveFilters ?? false)
            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap bg-light p-2 rounded-2 border">
                <span class="fs-11 text-muted fw-bold text-uppercase me-1"><i class="feather-filter me-1"></i>{{ __('hrms.offboarding_policies.active_filters') }}</span>
                @if($search)
                    <x-ui.badge variant="light" class="border bg-white text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        {{ __('hrms.offboarding_policies.label_search') }}: <strong>{{ $search }}</strong>
                        <a href="{{ route('hrms.offboarding-policies.index', array_merge($currentParams, ['search' => ''])) }}" class="text-muted ms-1"><i class="feather-x fs-10"></i></a>
                    </x-ui.badge>
                @endif
                @if($selectedCategory)
                    <x-ui.badge variant="light" class="border bg-white text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        {{ __('hrms.offboarding_policies.label_dept') }}: <strong>{{ $availableCategories[$selectedCategory] ?? $selectedCategory }}</strong>
                        <a href="{{ route('hrms.offboarding-policies.index', array_merge($currentParams, ['category' => ''])) }}" class="text-muted ms-1"><i class="feather-x fs-10"></i></a>
                    </x-ui.badge>
                @endif
                @if($selectedMandatory !== null && $selectedMandatory !== '')
                    <x-ui.badge variant="light" class="border bg-white text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        {{ __('hrms.offboarding_policies.label_requirement') }}: <strong>{{ $selectedMandatory == '1' ? __('hrms.offboarding_policies.mandatory') : __('hrms.offboarding_policies.optional') }}</strong>
                        <a href="{{ route('hrms.offboarding-policies.index', array_merge($currentParams, ['is_mandatory' => ''])) }}" class="text-muted ms-1"><i class="feather-x fs-10"></i></a>
                    </x-ui.badge>
                @endif
                @if($selectedStatus !== null && $selectedStatus !== '')
                    <x-ui.badge variant="light" class="border bg-white text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        {{ __('hrms.offboarding_policies.field_status') }}: <strong>{{ $selectedStatus == '1' ? __('hrms.offboarding_policies.active') : __('hrms.offboarding_policies.inactive') }}</strong>
                        <a href="{{ route('hrms.offboarding-policies.index', array_merge($currentParams, ['status' => ''])) }}" class="text-muted ms-1"><i class="feather-x fs-10"></i></a>
                    </x-ui.badge>
                @endif
                @if($selectedCompanyId)
                    @php $compName = $companies->firstWhere('id', $selectedCompanyId)?->company_name ?? __('hrms.offboarding_policies.label_company'); @endphp
                    <x-ui.badge variant="light" class="border bg-white text-dark px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        {{ __('hrms.offboarding_policies.label_company') }}: <strong>{{ $compName }}</strong>
                        <a href="{{ route('hrms.offboarding-policies.index', array_merge($currentParams, ['company_id' => ''])) }}" class="text-muted ms-1"><i class="feather-x fs-10"></i></a>
                    </x-ui.badge>
                @endif
                <a href="{{ route('hrms.offboarding-policies.index') }}" class="fs-11 text-danger ms-auto fw-bold text-decoration-none">{{ __('hrms.offboarding_policies.clear_all') }}</a>
            </div>
        @endif

        <!-- Flat Master Checklist Points Table -->
        <div class="table-responsive border rounded-3 bg-white mb-2" style="overflow-x: hidden;">
            <table class="table table-hover align-middle mb-0 text-dark w-100">
                <thead class="table-light fs-11 text-uppercase tracking-wider">
                    <tr>
                        <th class="ps-3 py-2.5 text-center text-nowrap" style="width: 45px;">{{ __('hrms.offboarding_policies.tbl_num') }}</th>
                        <th class="py-2.5" style="min-width: 220px;">
                            <div>{{ __('hrms.offboarding_policies.tbl_point_guidelines') }}</div>
                            <div class="text-muted fw-normal text-capitalize" style="font-size: 10px;">{{ __('hrms.offboarding_policies.tbl_guidelines_sub') }}</div>
                        </th>
                        <th class="py-2.5 text-nowrap" style="width: 170px;">{{ __('hrms.offboarding_policies.tbl_responsible_dept') }}</th>
                        <th class="py-2.5 text-center text-nowrap" style="width: 140px;">{{ __('hrms.offboarding_policies.tbl_requirement') }}</th>
                        <th class="py-2.5 text-center text-nowrap" style="width: 130px;">{{ __('hrms.offboarding_policies.tbl_scope') }}</th>
                        <th class="py-2.5 text-center text-nowrap" style="width: 80px;">{{ __('hrms.offboarding_policies.tbl_status') }}</th>
                        <th class="text-end pe-3 py-2.5 text-nowrap" style="width: 85px;">{{ __('hrms.offboarding_policies.tbl_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse($clearanceTemplates as $tpl)
                        @php
                            $isObj = is_object($tpl);
                            $itemId = $isObj ? $tpl->id : ($tpl['id'] ?? 'item_' . $loop->index);
                            $itemName = $isObj ? $tpl->item_name : $tpl['item_name'];
                            $itemDesc = $isObj ? ($tpl->description ?? '') : ($tpl['description'] ?? '');
                            $catKey = $isObj ? $tpl->clearance_category : ($tpl['clearance_category'] ?? 'hr_operations');
                            $catName = $isObj ? ($tpl->category_name ?? null) : ($tpl['category_name'] ?? null);
                            $meta = \App\Domains\HRMS\Models\ExitClearanceTemplate::getCategoryMetadata($catKey, $catName);
                            $isMandatory = (bool)($isObj ? $tpl->is_mandatory : ($tpl['is_mandatory'] ?? true));
                            $isActive = (bool)($isObj ? $tpl->status : ($tpl['status'] ?? true));
                            $sortOrder = $isObj ? $tpl->sort_order : ($tpl['sort_order'] ?? $loop->iteration);
                            $itemComp = $isObj && $tpl->company ? $tpl->company->company_name : null;
                        @endphp
                        <tr class="policy-point-row {{ !$isActive ? 'opacity-75 bg-light' : '' }}">
                            <!-- Sort Order -->
                            <td class="ps-3 py-2.5 text-center text-muted fw-semibold fs-12 text-nowrap">
                                {{ $sortOrder ?: (($clearanceTemplates->currentPage() - 1) * $clearanceTemplates->perPage() + $loop->iteration) }}
                            </td>

                            <!-- Item Name & Description (Wraps to next lines naturally) -->
                            <td class="py-2.5">
                                <div class="fw-bold text-dark fs-13 mb-0.5 lh-sm text-wrap">{{ $itemName }}</div>
                                @if($itemDesc)
                                    <div class="text-muted fs-11 lh-base text-wrap">{{ $itemDesc }}</div>
                                @endif
                            </td>

                            <!-- Responsible Department / Authority -->
                            <td class="py-2.5 text-nowrap">
                                <x-ui.badge soft :variant="$meta['color']" class="badge-authority">
                                    <i class="{{ $meta['icon'] }} fs-12"></i>
                                    {{ $meta['name'] }}
                                </x-ui.badge>
                            </td>

                            <!-- Sign-off Requirement -->
                            <td class="py-2.5 text-center text-nowrap">
                                @if($isMandatory)
                                    <x-ui.badge soft variant="danger" class="fs-11 px-2.5 py-1 fw-bold">
                                        <i class="feather-alert-circle me-1"></i> {{ __('hrms.offboarding_policies.mandatory') }}
                                    </x-ui.badge>
                                @else
                                    <x-ui.badge soft variant="secondary" class="fs-11 px-2.5 py-1">
                                        {{ __('hrms.offboarding_policies.optional') }}
                                    </x-ui.badge>
                                @endif
                            </td>

                            <!-- Scope -->
                            <td class="py-2.5 text-center text-nowrap">
                                @if($itemComp)
                                    <x-ui.badge soft variant="info" class="fs-11 px-2 py-0.5 text-truncate" style="max-width: 130px;" title="{{ $itemComp }}">
                                        <i class="feather-briefcase me-1"></i> {{ $itemComp }}
                                    </x-ui.badge>
                                @else
                                    <x-ui.badge soft variant="secondary" class="fs-11 px-2 py-0.5">
                                        {{ __('hrms.offboarding_policies.all_companies') }}
                                    </x-ui.badge>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-2.5 text-center text-nowrap">
                                <x-ui.status-badge :status="$isActive ? 'active' : 'inactive'" :label="$isActive ? __('hrms.offboarding_policies.active') : __('hrms.offboarding_policies.inactive')" />
                            </td>

                            <!-- Actions -->
                            <td class="text-end pe-3 py-2.5 text-nowrap">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    @if(is_numeric($itemId))
                                        <x-ui.icon-btn variant="light-brand" size="sm" icon="feather-edit-2" title="{{ __('hrms.offboarding_policies.edit_point') }}" data-bs-toggle="modal" data-bs-target="#editTemplateModal_{{ $itemId }}" />
                                        <x-ui.icon-btn variant="soft-danger" size="sm" icon="feather-trash-2" title="{{ __('hrms.offboarding_policies.delete_point') }}" onclick="openDeleteModal('{{ route('hrms.offboarding-policies.destroy', $itemId) }}', '{{ addslashes($itemName) }}')" />
                                    @else
                                        <x-ui.badge soft variant="secondary" class="fs-10 px-2 py-1">{{ __('hrms.offboarding_policies.system_default') }}</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="feather-clipboard fs-24"></i>
                                </div>
                                <h6 class="fw-bold mb-1 text-dark">{{ __('hrms.offboarding_policies.empty_title') }}</h6>
                                <p class="fs-13 mb-3 text-muted">{{ __('hrms.offboarding_policies.empty_desc') }}</p>
                                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold px-3 py-1.5">
                                    {{ __('hrms.offboarding_policies.btn_add_point') }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Standard ERP Pagination Component -->
        @if(isset($clearanceTemplates) && ($clearanceTemplates instanceof \Illuminate\Contracts\Pagination\Paginator || $clearanceTemplates instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator))
            <div class="mt-2">
                <x-ui.pagination :paginator="$clearanceTemplates" />
            </div>
        @endif
    </div>
</div>

<!-- ── MODALS SECTION ── -->

<!-- 1. Add Checklist Point Modal (Common UI Modal & Form) -->
<x-ui.modal 
    id="createTemplateModal" 
    title="<i class='feather-plus-circle text-primary me-2'></i>{{ __('hrms.offboarding_policies.modal_add_title') }}" 
    size="lg" 
    formAction="{{ route('hrms.offboarding-policies.store') }}" 
    formMethod="POST" 
    :centered="true" 
    :scrollable="true"
>
    <div class="row g-3">
        <!-- Point Name / Task -->
        <div class="col-12">
            <x-ui.modal-form-ui 
                type="input" 
                :label="__('hrms.offboarding_policies.field_point_title')" 
                name="item_name" 
                :placeholder="__('hrms.offboarding_policies.field_point_title_placeholder')" 
                :required="true" 
            />
        </div>

        <!-- Department Authority -->
        <div class="col-md-6">
            <x-ui.modal-form-ui 
                type="select" 
                :label="__('hrms.offboarding_policies.field_dept_authority')" 
                name="clearance_category" 
                :required="true" 
                :searchable="true"
            >
                <option value="it_assets">{{ __('hrms.offboarding_policies.dept_it_assets') }}</option>
                <option value="facilities_admin">{{ __('hrms.offboarding_policies.dept_facilities_admin') }}</option>
                <option value="finance_payroll">{{ __('hrms.offboarding_policies.dept_finance_payroll') }}</option>
                <option value="hr_operations">{{ __('hrms.offboarding_policies.dept_hr_operations') }}</option>
                <option value="reporting_manager">{{ __('hrms.offboarding_policies.dept_reporting_manager') }}</option>
                <option value="legal_compliance">{{ __('hrms.offboarding_policies.dept_legal_compliance') }}</option>
                <option value="other">{{ __('hrms.offboarding_policies.dept_other') }}</option>
            </x-ui.modal-form-ui>
        </div>

        <!-- Company Scope -->
        <div class="col-md-6">
            <x-ui.modal-form-ui 
                type="select" 
                :label="__('hrms.offboarding_policies.field_company_scope')" 
                name="company_id" 
                :searchable="true"
            >
                <option value="">{{ __('hrms.offboarding_policies.filter_all_companies') }}</option>
                @foreach($companies as $comp)
                    <option value="{{ $comp->id }}" @selected($selectedCompanyId == $comp->id)>{{ $comp->company_name }}</option>
                @endforeach
            </x-ui.modal-form-ui>
        </div>

        <!-- Description / Instructions -->
        <div class="col-12">
            <x-ui.modal-form-ui 
                type="textarea" 
                :label="__('hrms.offboarding_policies.field_guidelines')" 
                name="description" 
                rows="2" 
                :placeholder="__('hrms.offboarding_policies.field_guidelines_placeholder')" 
            />
        </div>

        <!-- Status Dropdown -->
        <div class="col-md-6">
            <x-ui.modal-form-ui 
                type="select" 
                :label="__('hrms.offboarding_policies.field_status')" 
                name="status" 
                :searchable="false"
            >
                <option value="1" selected>{{ __('hrms.offboarding_policies.active') }}</option>
                <option value="0">{{ __('hrms.offboarding_policies.inactive') }}</option>
            </x-ui.modal-form-ui>
        </div>

        <!-- Options: Mandatory Sign-off -->
        <div class="col-md-6 d-flex align-items-center pt-3">
            <x-ui.checkbox 
                :label="__('hrms.offboarding_policies.field_mandatory_checkbox')" 
                name="is_mandatory" 
                id="create_is_mandatory" 
                value="1" 
                :checked="true" 
            />
        </div>
    </div>

    <x-slot:footer>
        <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="border px-3 fw-semibold">
            {{ __('hrms.common.cancel') }}
        </x-ui.button>
        <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="px-4 fw-bold">
            {{ __('hrms.offboarding_policies.btn_save_point') }}
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>

<!-- 2. Edit Point Modals (Common UI Modal & Form) -->
@foreach($clearanceTemplates as $tpl)
    @if(is_object($tpl) && is_numeric($tpl->id))
        <x-ui.modal 
            id="editTemplateModal_{{ $tpl->id }}" 
            title="<i class='feather-edit-2 text-primary me-2'></i>{{ __('hrms.offboarding_policies.modal_edit_title') }}" 
            size="lg" 
            formAction="{{ route('hrms.offboarding-policies.update', $tpl->id) }}" 
            formMethod="PUT" 
            :centered="true" 
            :scrollable="true"
        >
            <div class="row g-3">
                <!-- Point Title -->
                <div class="col-12">
                    <x-ui.modal-form-ui 
                        type="input" 
                        :label="__('hrms.offboarding_policies.field_point_title')" 
                        name="item_name" 
                        :value="$tpl->item_name" 
                        :required="true" 
                    />
                </div>

                <!-- Department Authority -->
                <div class="col-md-6">
                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('hrms.offboarding_policies.field_dept_authority')" 
                        name="clearance_category" 
                        id="edit_cat_{{ $tpl->id }}" 
                        :required="true" 
                        :searchable="true"
                    >
                        <option value="it_assets" @selected($tpl->clearance_category === 'it_assets')>{{ __('hrms.offboarding_policies.dept_it_assets') }}</option>
                        <option value="facilities_admin" @selected($tpl->clearance_category === 'facilities_admin')>{{ __('hrms.offboarding_policies.dept_facilities_admin') }}</option>
                        <option value="finance_payroll" @selected($tpl->clearance_category === 'finance_payroll')>{{ __('hrms.offboarding_policies.dept_finance_payroll') }}</option>
                        <option value="hr_operations" @selected($tpl->clearance_category === 'hr_operations')>{{ __('hrms.offboarding_policies.dept_hr_operations') }}</option>
                        <option value="reporting_manager" @selected($tpl->clearance_category === 'reporting_manager')>{{ __('hrms.offboarding_policies.dept_reporting_manager') }}</option>
                        <option value="legal_compliance" @selected($tpl->clearance_category === 'legal_compliance')>{{ __('hrms.offboarding_policies.dept_legal_compliance') }}</option>
                        <option value="other" @selected($tpl->clearance_category === 'other')>{{ __('hrms.offboarding_policies.dept_other') }}</option>
                    </x-ui.modal-form-ui>
                </div>

                <!-- Company Scope -->
                <div class="col-md-6">
                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('hrms.offboarding_policies.field_company_scope')" 
                        name="company_id" 
                        id="edit_comp_{{ $tpl->id }}" 
                        :searchable="true"
                    >
                        <option value="">{{ __('hrms.offboarding_policies.filter_all_companies') }}</option>
                        @foreach($companies as $comp)
                            <option value="{{ $comp->id }}" @selected($tpl->company_id == $comp->id)>{{ $comp->company_name }}</option>
                        @endforeach
                    </x-ui.modal-form-ui>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <x-ui.modal-form-ui 
                        type="textarea" 
                        :label="__('hrms.offboarding_policies.field_guidelines')" 
                        name="description" 
                        rows="2" 
                        :value="$tpl->description" 
                    />
                </div>

                <!-- Status Dropdown -->
                <div class="col-md-6">
                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('hrms.offboarding_policies.field_status')" 
                        name="status" 
                        id="edit_stat_{{ $tpl->id }}" 
                        :searchable="false"
                    >
                        <option value="1" @selected($tpl->status == 1 || $tpl->status === true)>{{ __('hrms.offboarding_policies.active') }}</option>
                        <option value="0" @selected($tpl->status == 0 || $tpl->status === false)>{{ __('hrms.offboarding_policies.inactive') }}</option>
                    </x-ui.modal-form-ui>
                </div>

                <!-- Mandatory Sign-off -->
                <div class="col-md-6 d-flex align-items-center pt-3">
                    <x-ui.checkbox 
                        :label="__('hrms.offboarding_policies.field_mandatory_checkbox')" 
                        name="is_mandatory" 
                        id="edit_mand_{{ $tpl->id }}" 
                        value="1" 
                        :checked="(bool)$tpl->is_mandatory" 
                    />
                </div>
            </div>

            <x-slot:footer>
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="border px-3 fw-semibold">
                    {{ __('hrms.common.cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="px-4 fw-bold">
                    {{ __('hrms.offboarding_policies.btn_update_point') }}
                </x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endforeach

<!-- 3. Reset to Defaults Modal (Common UI Modal) -->
<x-ui.modal 
    id="resetTemplatesModal" 
    title="<div class='d-flex align-items-center gap-2'><div class='avatar avatar-xs bg-soft-warning text-warning rounded-circle d-flex align-items-center justify-content-center'><i class='feather-rotate-ccw fs-13'></i></div><span>{{ __('hrms.offboarding_policies.modal_reset_title') }}</span></div>" 
    size="md" 
    :static="true" 
    formAction="{{ route('hrms.offboarding-policies.reset') }}" 
    formMethod="POST"
>
    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
    <div class="d-flex align-items-start gap-3 mb-3">
        <div class="avatar avatar-md bg-soft-warning text-warning rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center mt-1">
            <i class="feather-alert-triangle fs-18"></i>
        </div>
        <div>
            <h6 class="fw-bold text-dark mb-1">{{ __('hrms.offboarding_policies.modal_reset_heading') }}</h6>
            <p class="fs-13 text-muted mb-0">
                {!! __('hrms.offboarding_policies.modal_reset_desc', ['scope' => $selectedCompanyId ? __('hrms.offboarding_policies.scope_company') : __('hrms.offboarding_policies.scope_global')]) !!}
            </p>
        </div>
    </div>
    
    <div class="p-2.5 bg-light rounded-3 border d-flex align-items-center gap-2">
        <i class="feather-info text-primary fs-14 flex-shrink-0"></i>
        <span class="fs-12 text-muted">{{ __('hrms.offboarding_policies.modal_reset_note') }}</span>
    </div>

    <x-slot:footer>
        <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="border">{{ __('hrms.common.cancel') }}</x-ui.button>
        <x-ui.button type="submit" variant="danger" icon="feather-rotate-ccw">
            {{ __('hrms.offboarding_policies.btn_confirm_reset') }}
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>

<!-- 4. Delete Single Item Modal (Common UI Modal) -->
<x-ui.modal 
    id="deleteModal" 
    title="<div class='d-flex align-items-center gap-2'><div class='avatar avatar-xs bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center'><i class='feather-trash-2 fs-13'></i></div><span>{{ __('hrms.offboarding_policies.modal_delete_title') }}</span></div>" 
    size="md" 
    :static="true" 
    :showFooter="true"
>
    <form method="POST" id="deleteItemForm" action="">
        @csrf
        @method('DELETE')
        <div class="d-flex align-items-start gap-3">
            <div class="avatar avatar-md bg-soft-danger text-danger rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center mt-1">
                <i class="feather-trash-2 fs-18"></i>
            </div>
            <div>
                <p class="text-dark fs-13 mb-1">{!! __('hrms.offboarding_policies.modal_delete_prompt', ['name' => '<strong id="deleteItemName" class="text-danger"></strong>']) !!}</p>
                <p class="text-muted fs-12 mb-0">{{ __('hrms.offboarding_policies.modal_delete_desc') }}</p>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-end gap-2 w-100">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal" class="border">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" form="deleteItemForm" variant="danger" icon="feather-trash-2">
                {{ __('hrms.offboarding_policies.btn_confirm_delete') }}
            </x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.modal>

<script>
    function openDeleteModal(url, itemName) {
        const form = document.getElementById('deleteItemForm');
        const nameEl = document.getElementById('deleteItemName');
        const modalEl = document.getElementById('deleteModal');
        if (form && nameEl && modalEl) {
            form.action = url;
            nameEl.textContent = itemName;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }
</script>
@endsection
