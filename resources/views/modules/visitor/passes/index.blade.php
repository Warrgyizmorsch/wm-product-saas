@extends('layouts.duralux')

@section('title', __('visitor.visitor_passes') . ' | SaaS ERP')
@section('page-title', __('visitor.visitor_passes'))
@section('breadcrumb', __('visitor.visitor_management') . ' > ' . __('visitor.visitor_passes'))

@push('styles')
<style>
    .visitor-avatar {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
    }
    .visitor-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
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
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('visitor.passes.create') }}" variant="primary" icon="feather-plus">
            {{ __('visitor.new_visitor_pass') }}
        </x-ui.button>
    </div>
@endsection

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="feather-check-circle fs-18 text-success me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Main Panel (Lead Module Standard) -->
    <div class="erp-single-panel">
        
        {{-- Header: Title, Search & Filter Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('visitor.visitor_passes') }}</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (CRM/HRMS Style) -->
                <form method="GET" action="{{ route('visitor.passes.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 250px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('visitor.search_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('visitor.passes.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Filter Component (Lead Module Common Filter) -->
                <form method="GET" action="{{ route('visitor.passes.index') }}" class="d-inline">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <x-ui.filter :label="__('crm.filter') ?? 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') ?? 'Filter Options' }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">— All Statuses —</option>
                                <option value="Checked-In" {{ request('status') === 'Checked-In' ? 'selected' : '' }}>{{ __('visitor.statuses.Checked-In') }}</option>
                                <option value="Expected" {{ request('status') === 'Expected' ? 'selected' : '' }}>{{ __('visitor.statuses.Expected') }}</option>
                                <option value="Checked-Out" {{ request('status') === 'Checked-Out' ? 'selected' : '' }}>{{ __('visitor.statuses.Checked-Out') }}</option>
                                <option value="Waiting Approval" {{ request('status') === 'Waiting Approval' ? 'selected' : '' }}>{{ __('visitor.statuses.Waiting Approval') }}</option>
                                <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>{{ __('visitor.statuses.Approved') }}</option>
                                <option value="Rejected" {{ request('status') === 'Rejected' ? 'selected' : '' }}>{{ __('visitor.statuses.Rejected') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.purpose_of_visit') }}</label>
                            <x-ui.odoo-form-ui type="select" name="purpose">
                                <option value="">— All Purposes —</option>
                                <option value="Meeting" {{ request('purpose') === 'Meeting' ? 'selected' : '' }}>{{ __('visitor.purposes.Meeting') }}</option>
                                <option value="Interview" {{ request('purpose') === 'Interview' ? 'selected' : '' }}>{{ __('visitor.purposes.Interview') }}</option>
                                <option value="Vendor" {{ request('purpose') === 'Vendor' ? 'selected' : '' }}>{{ __('visitor.purposes.Vendor') }}</option>
                                <option value="Delivery" {{ request('purpose') === 'Delivery' ? 'selected' : '' }}>{{ __('visitor.purposes.Delivery') }}</option>
                                <option value="Audit" {{ request('purpose') === 'Audit' ? 'selected' : '' }}>{{ __('visitor.purposes.Audit') }}</option>
                                <option value="Personal" {{ request('purpose') === 'Personal' ? 'selected' : '' }}>{{ __('visitor.purposes.Personal') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.select_host') }}</label>
                            <x-ui.odoo-form-ui type="select" name="host_user_id">
                                <option value="">— All Hosts —</option>
                                @foreach($hosts ?? [] as $h)
                                    <option value="{{ $h->id }}" {{ (string)request('host_user_id') === (string)$h->id ? 'selected' : '' }}>{{ $h->name }} ({{ $h->email }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') ?? 'Date From' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') ?? 'Date To' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('visitor.passes.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') ?? 'Reset' }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') ?? 'Apply Filters' }}</button>
                        </div>
                    </x-ui.filter>
                </form>

                <x-ui.button href="{{ route('visitor.passes.create') }}" variant="primary" icon="feather-plus">
                    {{ __('visitor.new_visitor_pass') }}
                </x-ui.button>
            </div>
        </div>

        <!-- Status Tabs (Using Common Horizontal Tabs Component) -->
        @php
            $visitorPassesTabs = [
                [
                    'id' => 'tab-all-passes',
                    'label' => __('visitor.all_passes') . ' (' . ($counts['all'] ?? $passes->total()) . ')',
                    'url' => route('visitor.passes.index', array_merge(request()->except('status', 'page'), [])),
                    'active' => empty($status),
                ],
                [
                    'id' => 'tab-checked-in',
                    'label' => __('visitor.inside_premises') . ' (' . ($counts['checked_in'] ?? 0) . ')',
                    'url' => route('visitor.passes.index', array_merge(request()->except('status', 'page'), ['status' => 'Checked-In'])),
                    'active' => $status === 'Checked-In',
                ],
                [
                    'id' => 'tab-expected',
                    'label' => __('visitor.expected') . ' (' . ($counts['expected'] ?? 0) . ')',
                    'url' => route('visitor.passes.index', array_merge(request()->except('status', 'page'), ['status' => 'Expected'])),
                    'active' => $status === 'Expected',
                ],
                [
                    'id' => 'tab-checked-out',
                    'label' => __('visitor.checked_out') . ' (' . ($counts['checked_out'] ?? 0) . ')',
                    'url' => route('visitor.passes.index', array_merge(request()->except('status', 'page'), ['status' => 'Checked-Out'])),
                    'active' => $status === 'Checked-Out',
                ],
            ];
        @endphp
        <x-ui.horizontal-tabs id="visitorPassesTabs" class="mb-3" :tabs="$visitorPassesTabs" />

        <!-- Passes Data Table (Using Common x-ui.odoo-form-ui table) -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="passesTable" class="mb-0">
                <thead>
                    <tr>
                        <th style="width: 35px;" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 14%;">{{ __('visitor.pass_number') }}</th>
                        <th style="width: 20%;">{{ __('visitor.visitor_name') }}</th>
                        <th style="width: 15%;">{{ __('visitor.company') }}</th>
                        <th style="width: 15%;">{{ __('visitor.select_host') }}</th>
                        <th style="width: 12%;">{{ __('visitor.purpose_of_visit') }}</th>
                        <th style="width: 12%;">{{ __('visitor.check_in_time') }}</th>
                        <th style="width: 10%;" class="text-end pe-3">{{ __('visitor.fee_charge') }}</th>
                        <th style="width: 10%;">{{ __('visitor.status') }}</th>
                        <th style="width: 5%;" class="text-end pe-3">{{ __('visitor.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($passes as $pass)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>

                            <!-- Pass # & Gate -->
                            <td>
                                <a href="{{ route('visitor.passes.show', $pass->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                    {{ $pass->pass_number }}
                                </a>
                                <div class="fs-11 text-muted">{{ $pass->gate_number ?? 'Gate 1' }} • {{ $pass->entry_type }}</div>
                            </td>

                            <!-- Visitor Avatar & Contact -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if(!empty($pass->visitor?->photo_url))
                                        <img src="{{ $pass->visitor->photo_url }}" class="visitor-avatar" alt="Photo">
                                    @else
                                        <div class="visitor-avatar-placeholder">
                                            {{ strtoupper(substr($pass->visitor?->full_name ?? 'V', 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark fs-13">{{ $pass->visitor?->full_name ?? 'N/A' }}</div>
                                        <div class="fs-11 text-muted">
                                            <i class="feather-phone me-1 text-muted"></i>{{ $pass->visitor?->phone ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Company -->
                            <td>
                                <div class="fw-medium text-dark">{{ $pass->visitor?->company_name ?: '—' }}</div>
                                @if(!empty($pass->visitor?->designation))
                                    <div class="fs-11 text-muted">{{ $pass->visitor->designation }}</div>
                                @endif
                            </td>

                            <!-- Host Employee -->
                            <td>
                                <div class="fw-semibold text-primary">
                                    <i class="feather-user me-1 text-muted"></i>{{ $pass->host?->name ?: 'Direct Reception' }}
                                </div>
                                <div class="fs-10 text-muted">{{ $pass->host?->email ?? '' }}</div>
                            </td>

                            <!-- Purpose & Belongings -->
                            <td>
                                <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                                    {{ __('visitor.purposes.' . $pass->purpose, [], null) ?? $pass->purpose }}
                                </span>
                                @if($pass->belongings && $pass->belongings->count() > 0)
                                    <span class="badge bg-soft-info text-info ms-1" title="Equipments Registered">
                                        <i class="feather-briefcase me-1"></i>{{ $pass->belongings->count() }}
                                    </span>
                                @endif
                            </td>

                            <!-- Timestamps -->
                            <td>
                                @if($pass->check_in_at)
                                    <div class="text-success fw-semibold fs-12">
                                        <i class="feather-log-in me-1"></i>{{ \Carbon\Carbon::parse($pass->check_in_at)->format('d M, h:i A') }}
                                    </div>
                                    @if($pass->check_out_at)
                                        <div class="text-danger fw-semibold fs-11 mt-0.5">
                                            <i class="feather-log-out me-1"></i>{{ \Carbon\Carbon::parse($pass->check_out_at)->format('d M, h:i A') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">
                                        {{ $pass->expected_arrival_at ? \Carbon\Carbon::parse($pass->expected_arrival_at)->format('d M, h:i A') : '—' }}
                                    </span>
                                @endif
                            </td>

                            <!-- Fee Amount -->
                            <td class="text-end pe-3">
                                <span class="fw-bold text-dark fs-12">{{ format_currency($pass->fee_amount ?? 0) }}</span>
                            </td>

                            <!-- Status Badge Component -->
                            <td>
                                @php
                                    $statusKey = match($pass->status) {
                                        'Checked-In'       => 'approved',
                                        'Checked-Out'      => 'completed',
                                        'Approved'         => 'active',
                                        'Waiting Approval' => 'pending_approval',
                                        'Rejected'         => 'rejected',
                                        default            => 'in_progress',
                                    };
                                @endphp
                                <x-ui.status-badge :status="$statusKey" :label="__('visitor.statuses.' . $pass->status, [], null) ?? $pass->status" dot />
                            </td>

                            <!-- Actions (Using Common x-ui.action-dropdown) -->
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('visitor.passes.show', $pass->id)">
                                    <x-slot:extraActions>
                                        @if($pass->status === 'Expected' || $pass->status === 'Approved')
                                            <form method="POST" action="{{ route('visitor.passes.check-in', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-success" title="{{ __('visitor.check_in') }}">
                                                    <i class="feather-log-in"></i>
                                                </button>
                                            </form>
                                        @elseif($pass->status === 'Waiting Approval')
                                            <span class="action-dropdown-btn text-warning" title="Waiting Host Approval" style="cursor: not-allowed; opacity: 0.75;">
                                                <i class="feather-clock"></i>
                                            </span>
                                        @elseif($pass->status === 'Checked-In')
                                            <form method="POST" action="{{ route('visitor.passes.check-out', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-danger" title="{{ __('visitor.check_out') }}">
                                                    <i class="feather-log-out"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </x-slot:extraActions>

                                    <li>
                                        <a href="{{ route('visitor.passes.show', $pass->id) }}" class="dropdown-item">
                                            <i class="feather-printer me-2 text-primary fs-12"></i> {{ __('visitor.print_badge') }}
                                        </a>
                                    </li>
                                    @if($pass->status === 'Checked-In')
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.check-out', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-log-out me-2 text-danger fs-12"></i> {{ __('visitor.check_out') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-credit-card fs-1 d-block mb-3 text-light"></i>
                                <x-ui.button href="{{ route('visitor.passes.create') }}" variant="primary" icon="feather-plus" class="mt-3">
                                    {{ __('visitor.new_visitor_pass') }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination (Lead Module Standard Style) -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$passes->currentPage()"
                :totalPages="$passes->lastPage()"
                :totalResults="$passes->total()"
                :perPage="$passes->perPage()" />
        </div>

    </div>

@endsection
