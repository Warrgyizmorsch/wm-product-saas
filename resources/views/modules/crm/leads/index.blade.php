@extends('layouts.duralux')

@section('title', __('crm.crm_leads') . ' | SaaS ERP')
@section('page-title', __('crm.crm_leads'))
@section('breadcrumb', __('crm.crm_leads'))

@section('page-actions')
    <x-ui.button href="{{ route('crm.leads.create') }}" variant="primary" icon="feather-plus">
        {{ __('crm.add_new_call_lead') }}
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'call_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        {{-- 1. Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="fw-bold text-dark mb-0">{{ __('crm.leads_listing') }}</h5>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Outside Search Box (HRMS Style) -->
                    <form method="GET" action="{{ route('crm.leads.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                        @foreach(request()->except(['search', 'page']) as $k => $v)
                            @if(is_scalar($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                        <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_placeholder_leads') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                        @if(request('search'))
                            <a href="{{ route('crm.leads.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                                <i class="feather-x fs-12"></i>
                            </a>
                        @endif
                    </form>

                    <x-ui.view-switcher />
                    <x-ui.sort-dropdown :label="__('crm.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_call_date_latest') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_call_date_oldest') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_company_name_az') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_company_name_za') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'duplicates', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'duplicates' ? 'active' : '' }}">
                            <span class="text-danger fw-semibold"><i class="feather-copy me-1"></i>Group Side-by-Side Duplicates</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_expected_amount_desc') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_expected_amount_asc') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <form method="GET" action="{{ route('crm.leads.index') }}" class="d-inline">
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_placeholder_leads')" value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.priority') }}</label>
                                <x-ui.odoo-form-ui type="select" name="priority">
                                    <option value="">{{ __('crm.all_priorities') }}</option>
                                    <option value="Low" {{ request('priority') === 'Low' ? 'selected' : '' }}>{{ __('crm.priorities.Low') }}</option>
                                    <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>{{ __('crm.priorities.Medium') }}</option>
                                    <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>{{ __('crm.priorities.High') }}</option>
                                    <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>{{ __('crm.priorities.Urgent') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.segment') }}</label>
                                <x-ui.odoo-form-ui type="select" name="segment">
                                    <option value="">{{ __('crm.all_segments') }}</option>
                                    <option value="SME" {{ request('segment') === 'SME' ? 'selected' : '' }}>{{ __('crm.segments.SME') }}</option>
                                    <option value="Mid-Market" {{ request('segment') === 'Mid-Market' ? 'selected' : '' }}>{{ __('crm.segments.Mid-Market') }}</option>
                                    <option value="Enterprise" {{ request('segment') === 'Enterprise' ? 'selected' : '' }}>{{ __('crm.segments.Enterprise') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('crm.all_statuses') }}</option>
                                    <option value="New" {{ request('status') === 'New' ? 'selected' : '' }}>{{ __('crm.statuses.New') }}</option>
                                    <option value="Qualified" {{ request('status') === 'Qualified' ? 'selected' : '' }}>{{ __('crm.statuses.Qualified') }}</option>
                                    <option value="Won" {{ request('status') === 'Won' ? 'selected' : '' }}>{{ __('crm.statuses.Won') }}</option>
                                    <option value="Lost" {{ request('status') === 'Lost' ? 'selected' : '' }}>{{ __('crm.statuses.Lost') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Lead Owner</label>
                                <x-ui.odoo-form-ui type="select" name="lead_owner_id">
                                    <option value="">All Lead Owners</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" {{ (string)request('lead_owner_id') === (string)$u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Quotation Status</label>
                                <x-ui.odoo-form-ui type="select" name="quotation_status">
                                    <option value="">All Leads</option>
                                    <option value="with_quotation" {{ request('quotation_status') === 'with_quotation' ? 'selected' : '' }}>With Quotation</option>
                                    <option value="without_quotation" {{ request('quotation_status') === 'without_quotation' ? 'selected' : '' }}>Without Quotation</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date From</label>
                                    <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') ?? request('start_date') }}" />
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date To</label>
                                    <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') ?? request('end_date') }}" />
                                </div>
                            </div>
                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>

                    <div class="dropdown d-inline-block">
                        <a href="javascript:void(0)" class="action-dropdown-btn dropdown-toggle-custom" :title="__('crm.import_export_options')">
                            <i class="feather feather-paperclip"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end fs-13 shadow-lg">
                            <li>
                                <a href="{{ route('crm.leads.export') }}" class="dropdown-item">
                                    <i class="feather-download me-2 text-muted fs-12"></i>{{ __('crm.export_excel') }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('crm.leads.downloadSample') }}" class="dropdown-item">
                                    <i class="feather-file-text me-2 text-muted fs-12"></i>{{ __('crm.download_sample') }}
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                                    <i class="feather-upload me-2 text-muted fs-12"></i>{{ __('crm.import') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- 2. Status Tabs Strip --}}
            @php
                $activeStatus = request('status');
                $isDuplicatesOnly = request('duplicates_only') === '1';
                $isAll = !$activeStatus && !$isDuplicatesOnly;
            @endphp
            <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
                <div class="d-flex align-items-center gap-1 overflow-x-auto" style="scrollbar-width: thin;">
                    <a href="{{ request()->fullUrlWithQuery(['status' => null, 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $isAll ? 'active' : '' }}">
                        ALL ({{ $totalLeadsCount ?? $leads->total() }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'New', 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $activeStatus === 'New' ? 'active' : '' }}">
                        UNTOUCHED LEADS ({{ $statusCounts['New'] ?? 0 }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Qualified', 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $activeStatus === 'Qualified' ? 'active' : '' }}">
                        QUALIFIED ({{ $statusCounts['Qualified'] ?? 0 }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Won', 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $activeStatus === 'Won' ? 'active' : '' }}">
                        WON ({{ $statusCounts['Won'] ?? 0 }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Lost', 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $activeStatus === 'Lost' ? 'active' : '' }}">
                        LOST ({{ $statusCounts['Lost'] ?? 0 }})
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['duplicates_only' => '1', 'status' => null]) }}"
                       class="crm-status-tab crm-status-tab--duplicates {{ $isDuplicatesOnly ? 'active' : '' }}">
                        <i class="feather-copy me-1 fs-11"></i>DUPLICATES ({{ $duplicatesCount ?? 0 }})
                    </a>
                </div>
            </div>

        {{-- 3. Table --}}
        <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="leadsTable" class="mb-0">
                    <thead>
                        <tr style="background-color: #e8ecf1 !important;">
                            <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th style="width: 10%; background-color: #e8ecf1 !important;">{{ __('crm.call_date_time') }}</th>
                            <th style="width: 17%; background-color: #e8ecf1 !important;">{{ __('crm.lead_company') }}</th>
                            <th style="width: 13%; background-color: #e8ecf1 !important;">Lead Owner</th>
                            <th style="width: 15%; background-color: #e8ecf1 !important;">{{ __('crm.phone_email') }}</th>
                            <th style="width: 11%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.value_est_sale') }}</th>
                            <th style="width: 16%; background-color: #e8ecf1 !important;">Details</th>
                            <th style="width: 8%; background-color: #e8ecf1 !important;">Quotation</th>
                            <th style="width: 8%; background-color: #e8ecf1 !important;">{{ __('crm.status') }}</th>
                            <th style="width: 3%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary me-2">
                                            <i class="feather-calendar"></i>
                                        </div>
                                        <div>
                                            <span class="d-block fw-semibold text-dark">{{ $lead->call_date ? $lead->call_date->format('d/m/Y') : 'N/A' }}</span>
                                            <span class="text-muted fs-11">{{ $lead->call_date ? $lead->call_date->format('h:i A') : 'N/A' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        <span class="fw-bold text-dark">{{ $lead->company_name }}</span>
                                        <span class="badge bg-light text-primary border font-monospace px-1.5 py-0.5 fs-11">{{ $lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)) }}</span>
                                        @if(!empty($lead->is_duplicate) && (request('duplicates_only') === '1' || request('sort_by') === 'duplicates'))
                                            <span class="duplicate-indicator ms-1" 
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  data-bs-html="true" 
                                                  data-bs-custom-class="custom-white-tooltip" 
                                                  title="<div class='text-start'><div class='fw-bold text-dark fs-12 mb-1'><i class='feather-copy text-warning me-1'></i>Duplicate Lead</div><div class='text-muted fs-11 mb-1'>Duplicate of Lead <strong class='text-dark'>#{{ $lead->duplicate_of_id }}</strong></div><div class='text-muted fs-11'>Reason: <strong class='text-dark'>{{ $lead->duplicate_reason }}</strong></div></div>">
                                                <a href="{{ route('crm.leads.show', $lead->duplicate_of_id) }}" class="text-warning text-decoration-none d-inline-flex align-items-center p-1 rounded hover-bg-warning-soft" onclick="event.stopPropagation();">
                                                    <i class="feather-copy fs-13"></i>
                                                </a>
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-muted fs-11"><i class="feather-user me-1 fs-10 text-primary"></i>{{ $lead->contact_person ?: 'N/A' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($lead->owner?->name ?: 'Unassigned') }}&background=1e40af&color=ffffff&size=64&bold=true" 
                                             alt="{{ $lead->owner?->name ?: 'Lead Owner' }}" 
                                             class="rounded-circle me-2 border shadow-xs" 
                                             style="width: 28px; height: 28px; object-fit: cover;">
                                        <div>
                                            <span class="d-block fw-semibold text-dark fs-12" style="line-height: 1.2;">{{ $lead->owner?->name ?: 'Unassigned' }}</span>
                                            <span class="text-muted fs-10 d-block">{{ $lead->owner?->email ?: '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($lead->phone)
                                        <span class="d-block text-dark"><i class="feather-phone fs-11 me-1 text-muted"></i>{{ $lead->phone }}</span>
                                    @endif
                                    @if ($lead->email)
                                        <span class="text-muted fs-11 d-block"><i class="feather-mail fs-11 me-1 text-muted"></i>{{ $lead->email }}</span>
                                    @endif
                                    @php
                                        $addlContacts = $lead->additional_contacts ?: [];
                                        $firstAddl = $addlContacts[0] ?? null;
                                    @endphp
                                    @if (!empty($firstAddl))
                                        <small class="text-secondary d-block fs-10 mt-1" title="Additional Contact">
                                            <i class="feather-user-plus me-1 text-primary"></i>
                                            {{ !empty($firstAddl['name']) ? $firstAddl['name'] . ': ' : '' }}
                                            {{ $firstAddl['phone'] ?? ($firstAddl['email'] ?? '') }}
                                            @if(count($addlContacts) > 1)
                                                <span class="badge bg-soft-primary text-primary fs-9 ms-1">+{{ count($addlContacts) - 1 }} more</span>
                                            @endif
                                        </small>
                                    @endif
                                    @if (!$lead->phone && !$lead->email && empty($firstAddl))
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <span class="fw-bold text-dark d-block mb-1">{{ $lead->expected_amount ? '₹' . number_format($lead->expected_amount, 2) : '—' }}</span>
                                    @if($lead->expected_sale_date)
                                        <span class="text-muted fs-11"><i class="feather-calendar me-1 fs-10 text-success"></i>{{ $lead->expected_sale_date->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1 fs-11">
                                        @if($lead->source && $lead->source !== 'Select an Option')
                                            <div><span class="text-muted">Source:</span> <span class="fw-semibold text-dark">{{ $lead->source }}</span></div>
                                        @endif
                                        @php
                                            $currentPriority = ($lead->priority && $lead->priority !== 'Select an Option') ? $lead->priority : '';
                                            $starsCount = match($currentPriority) {
                                                'Low' => 1,
                                                'Medium' => 2,
                                                'High' => 3,
                                                'Urgent' => 4,
                                                default => 0,
                                            };
                                            $starLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
                                            $badgeClasses = match($currentPriority) {
                                                'Low' => 'bg-soft-success text-success',
                                                'Medium' => 'bg-soft-warning text-warning',
                                                'High' => 'bg-soft-danger text-danger',
                                                'Urgent' => 'bg-danger text-white',
                                                default => 'bg-soft-secondary text-secondary',
                                            };
                                        @endphp
                                        <div class="d-flex align-items-center gap-1.5 my-0.5">
                                            <span class="text-muted fs-11">Priority:</span>
                                            <div class="star-rating-widget d-inline-flex align-items-center gap-1" id="starRating_{{ $lead->id }}" data-current-stars="{{ $starsCount }}" data-current-priority="{{ $currentPriority }}">
                                                @for($i = 1; $i <= 4; $i++)
                                                    @php $targetPriority = $starLabels[$i]; @endphp
                                                    <i class="feather-star star-icon {{ $i <= $starsCount ? 'active-star' : 'inactive-star' }}"
                                                       data-star="{{ $i }}"
                                                       data-priority="{{ $targetPriority }}"
                                                       data-lead-id="{{ $lead->id }}"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-placement="top"
                                                       title="{{ $targetPriority }} Priority ({{ $i }} Star{{ $i > 1 ? 's' : '' }})"
                                                       onclick="updateLeadPriority({{ $lead->id }}, '{{ $targetPriority }}', this)"></i>
                                                @endfor
                                            </div>
                                            <span class="badge fs-10 ms-1 priority-badge-{{ $lead->id }} {{ $badgeClasses }}">
                                                {{ $currentPriority ?: 'Unset' }}
                                            </span>
                                        </div>
                                        @if($lead->segment && $lead->segment !== 'Select an Option')
                                            <div><span class="text-muted">Segment:</span> <span class="fw-semibold text-dark">{{ __('crm.segments.' . $lead->segment) ?? $lead->segment }}</span></div>
                                        @endif
                                        @if((!$lead->source || $lead->source === 'Select an Option') && (!$lead->priority || $lead->priority === 'Select an Option') && (!$lead->segment || $lead->segment === 'Select an Option'))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $latestQuotation = $lead->quotations->sortByDesc('id')->first();
                                    @endphp
                                    @if ($latestQuotation)
                                        <span class="fw-semibold text-dark">{{ $latestQuotation->quotation_number }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($lead->is_customer || $lead->status === 'Won')
                                        <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i>Won</span>
                                    @else
                                        <div class="d-flex flex-column gap-1">
                                            <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="form-control status-select" data-select2-selector="status" style="width: 150px;">
                                                    @foreach(['New', 'Qualified', 'Won', 'Lost'] as $statusOption)
                                                        @php
                                                            $bgClass = 'bg-primary';
                                                            if($statusOption === 'Qualified') $bgClass = 'bg-teal';
                                                            elseif($statusOption === 'Won') $bgClass = 'bg-success';
                                                            elseif($statusOption === 'Lost') $bgClass = 'bg-danger';
                                                        @endphp
                                                        <option value="{{ $statusOption }}" data-bg="{{ $bgClass }}" {{ ($lead->status ?: 'New') === $statusOption ? 'selected' : '' }}>
                                                            {{ __('crm.statuses.' . $statusOption) ?? $statusOption }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.action-dropdown :viewUrl="route('crm.leads.show', $lead->id)">
                                        {{-- Edit --}}
                                        <li>
                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('crm.edit_lead') }}
                                            </a>
                                        </li>

                                        {{-- Convert to Quotation (for Qualified Leads) --}}
                                        @if (($lead->status ?: 'New') === 'Qualified' && $lead->getQuotations()->isEmpty())
                                            <li>
                                                <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-primary fw-semibold">
                                                        <i class="feather-file-plus me-2 text-primary fs-12"></i>Convert Quotation
                                                    </button>
                                                </form>
                                            </li>
                                        @endif

                                        @if(!empty($lead->is_duplicate))
                                            {{-- Qualify Lead (Only if Duplicate) --}}
                                            @if(($lead->status ?: 'New') !== 'Qualified' && ($lead->status ?: 'New') !== 'Won')
                                                <li>
                                                    <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item text-success fw-semibold">
                                                            <i class="feather-check-circle me-2 text-success fs-12"></i>Qualify (Genuine Lead)
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            {{-- Reject & Delete Lead (Only if Duplicate) --}}
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" id="deleteLeadForm_{{ $lead->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: 'Reject & Delete Lead', message: 'Are you sure you want to reject & delete lead &quot;{{ addslashes($lead->company_name) }}&quot; ({{ $lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)) }}) permanently?', variant: 'danger', confirmText: 'Reject & Delete', onConfirm: function() { document.getElementById('deleteLeadForm_{{ $lead->id }}').submit(); } })">
                                                        <i class="feather-x-circle me-2 text-danger fs-12"></i>Reject & Delete Lead
                                                    </button>
                                                </form>
                                            </li>
                                        @else
                                            {{-- Regular Delete (If Not Duplicate) --}}
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" id="deleteLeadForm_{{ $lead->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: 'Delete Lead', message: 'Are you sure you want to delete lead &quot;{{ addslashes($lead->company_name) }}&quot; ({{ $lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)) }}) permanently?', variant: 'danger', confirmText: 'Delete Lead', onConfirm: function() { document.getElementById('deleteLeadForm_{{ $lead->id }}').submit(); } })">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Lead
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-users fs-1 d-block mb-3 text-light"></i>
                                    {{ __('crm.no_leads') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

        {{-- 4. Pagination --}}
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$leads->currentPage()"
                :totalPages="$leads->lastPage()"
                :totalResults="$leads->total()"
                :perPage="$leads->perPage()" />
        </div>
    </div>

    {{-- Import Leads Modal --}}
    <x-ui.modal id="importLeadsModal" :title="__('crm.import_leads_modal_title')" :submitText="__('crm.import_file')" :centered="true">
        <form method="POST" action="{{ route('crm.leads.import') }}" enctype="multipart/form-data" id="importLeadsForm">
            @csrf
            <p class="fs-13 text-muted mb-3">{{ __('crm.import_leads_help_text') }}</p>
            <x-ui.odoo-form-ui type="file" name="file" :label="__('crm.excel_csv_file')" required :placeholder="__('crm.choose_file')" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
            <button type="submit" form="importLeadsForm" class="btn btn-primary">{{ __('crm.import_file') }}</button>
        </x-slot>
    </x-ui.modal>
@endsection

@push('styles')
    <!-- Select2 Theme Styles -->
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Status Tabs */
        .crm-status-tab {
            display: inline-flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #64748b;
            text-decoration: none;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            transition: all 0.2s ease;
        }
        .crm-status-tab:hover {
            color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
        }
        .crm-status-tab.active {
            color: var(--bs-primary);
            border-bottom-color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
        }
        .crm-status-tab--duplicates {
            color: #64748b;
        }
        .crm-status-tab--duplicates:hover {
            color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
        }
        .crm-status-tab--duplicates.active {
            color: var(--bs-primary);
            border-bottom-color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
        }

        /* Select2 compact for table */
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 2px 8px;
            height: auto;
            font-size: 11px;
            font-weight: 600;
        }
        .status-select + .select2-container {
            min-width: 125px !important;
            width: 125px !important;
        }

        /* Duplicate indicator icon */
        .duplicate-indicator {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            padding: 2px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        .duplicate-indicator:hover {
            background-color: rgba(245, 158, 11, 0.12);
        }

        /* Custom White Tooltip Popup Styling */
        .custom-white-tooltip .tooltip-inner {
            background-color: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08) !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
        }
        .custom-white-tooltip.bs-tooltip-top .tooltip-arrow::before {
            border-top-color: #ffffff !important;
        }
        .custom-white-tooltip.bs-tooltip-bottom .tooltip-arrow::before {
            border-bottom-color: #ffffff !important;
        }
        .custom-white-tooltip.bs-tooltip-start .tooltip-arrow::before {
            border-left-color: #ffffff !important;
        }
        .custom-white-tooltip.bs-tooltip-end .tooltip-arrow::before {
            border-right-color: #ffffff !important;
        }

        /* Star Rating Widget Styling */
        .star-rating-widget {
            cursor: pointer;
            user-select: none;
        }
        .star-rating-widget .star-icon {
            font-size: 13px;
            color: #cbd5e1;
            fill: transparent;
            transition: transform 0.15s ease, color 0.15s ease, fill 0.15s ease;
        }
        .star-rating-widget .star-icon.active-star {
            color: #f59e0b;
            fill: #f59e0b;
        }
        .star-rating-widget .star-icon.hovered-star {
            color: #f59e0b !important;
            fill: #f59e0b !important;
            transform: scale(1.25);
        }

        /* Spacious Table Alignment & Padding */
        #leadsTable td, #leadsTable th {
            padding: 10px 10px !important;
            vertical-align: middle !important;
        }
        #leadsTable thead th {
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            color: #475569 !important;
            white-space: nowrap !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        #leadsTable tbody tr {
            transition: background-color 0.15s ease;
        }
        #leadsTable tbody tr:hover {
            background-color: #f8fafc !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        window.updateLeadPriority = function(leadId, priority, el) {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            $.ajax({
                url: "{{ url('crm/leads') }}/" + leadId + '/priority',
                type: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                data: { priority: priority },
                success: function(res) {
                    if (res.success) {
                        var widget = $('#starRating_' + leadId);
                        var starsCount = priority === 'Low' ? 1 : (priority === 'Medium' ? 2 : (priority === 'High' ? 3 : 4));
                        widget.attr('data-current-stars', starsCount);
                        widget.attr('data-current-priority', priority);

                        widget.find('.star-icon').each(function() {
                            var s = parseInt($(this).attr('data-star'));
                            if (s <= starsCount) {
                                $(this).addClass('active-star').removeClass('inactive-star');
                            } else {
                                $(this).addClass('inactive-star').removeClass('active-star');
                            }
                        });

                        var badge = $('.priority-badge-' + leadId);
                        badge.text(priority);
                        badge.removeClass('bg-soft-success text-success bg-soft-warning text-warning bg-soft-danger text-danger bg-danger text-white bg-soft-secondary text-secondary');
                        if (priority === 'Low') badge.addClass('bg-soft-success text-success');
                        else if (priority === 'Medium') badge.addClass('bg-soft-warning text-warning');
                        else if (priority === 'High') badge.addClass('bg-soft-danger text-danger');
                        else if (priority === 'Urgent') badge.addClass('bg-danger text-white');
                    }
                },
                error: function(err) {
                    console.error('Failed to update priority:', err);
                }
            });
        };

        $(function () {
            // Auto submit status forms when changed in Select2
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Initialize Bootstrap tooltips for duplicate indicators
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) {
                return new bootstrap.Tooltip(el);
            });

            // Star rating hover effect
            $(document).on('mouseenter', '.star-rating-widget .star-icon', function() {
                var hoveredStar = parseInt($(this).attr('data-star'));
                var widget = $(this).closest('.star-rating-widget');
                widget.find('.star-icon').each(function() {
                    var s = parseInt($(this).attr('data-star'));
                    if (s <= hoveredStar) {
                        $(this).addClass('hovered-star');
                    } else {
                        $(this).removeClass('hovered-star');
                    }
                });
            }).on('mouseleave', '.star-rating-widget', function() {
                $(this).find('.star-icon').removeClass('hovered-star');
            });

            // Live Search filter for the Leads table
            $('#tableSearch').on('input', function() {
                var value = $(this).val().toLowerCase().trim();
                var visibleRows = 0;
                var totalRows = 0;

                $('#leadsTable tbody tr').each(function() {
                    if ($(this).hasClass('no-search-results')) {
                        return;
                    }
                    totalRows++;
                    var rowText = $(this).text().toLowerCase();
                    if (rowText.indexOf(value) > -1) {
                        $(this).show();
                        visibleRows++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#leadsTable tbody tr.no-search-results').remove();

                if (visibleRows === 0 && totalRows > 0) {
                    var noResultsText = '{{ __('crm.no_matching_leads', ['query' => '_QUERY_']) }}'.replace('_QUERY_', value);
                    $('#leadsTable tbody').append(
                        '<tr class="no-search-results"><td colspan="9" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>' + noResultsText + '</td></tr>'
                    );
                }
            });
        });
    </script>
@endpush
