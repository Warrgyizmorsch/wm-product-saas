@extends('layouts.duralux')

@section('title', __('crm.customers_sidebar') . ' | SaaS ERP')
@section('page-title', __('crm.customers_directory'))
@section('breadcrumb', __('crm.customers_sidebar'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="customers" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('crm.customers.export') }}" />
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#quickCreateModal_customer">
            {{ __('crm.new_customer') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    <x-ui.master-modals :masters="['customer']" />

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $activeStatus = request('status');
    @endphp

    <div class="erp-single-panel">
        {{-- 1. Header & Actions Toolbar (Matches Leads Listing Header 100%) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('crm.customers_directory') }}</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (HRMS & Leads Style) -->
                <form method="GET" action="{{ route('crm.customers.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_customer_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('crm.customers.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="{{ __('crm.clear_search') }}">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>


                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.latest_created') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.oldest_created') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.customer_name_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.customer_name_za') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('crm.customers.index') }}" class="d-inline">
                    @foreach(request()->except(['status', 'search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                        <div class="p-3 style-filter-menu" style="min-width: 250px;">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_customer_placeholder')" value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.customer_status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="all" @selected(request('status', 'all') === 'all')>{{ __('crm.all_customers') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>{{ __('crm.active_only') }}</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('crm.inactive_only') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="{{ route('crm.customers.index') }}" class="btn btn-xs btn-light">{{ __('crm.reset') }}</a>
                                <x-ui.button type="submit" variant="primary" size="xs">{{ __('crm.apply_filter') }}</x-ui.button>
                            </div>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Odoo Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="customersTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="background-color: #e8ecf1 !important;" class="ps-3">{{ __('crm.customer_name') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.email_address') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.phone_email') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.status') }}</th>
                        <th style="width: 1%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="ps-3">
                                <div>
                                    @php
                                        $accId = $customer->crmAccount?->id;
                                        $accRoute = $accId ? route('crm.accounts.show', $accId) : route('crm.customers.show', $customer);
                                    @endphp
                                    <a href="{{ $accRoute }}" class="fw-bold text-dark hover-primary d-block">{{ $customer->name }}</a>
                                    @if($customer->gstin)
                                        <span class="fs-10 font-monospace text-muted">GST: {{ $customer->gstin }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($customer->email)
                                    <span class="text-dark"><i class="feather-mail me-1 text-muted fs-11"></i>{{ $customer->email }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($customer->phone)
                                    <span class="text-dark"><i class="feather-phone me-1 text-muted fs-11"></i>{{ $customer->phone }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if (strtolower($customer->status) === 'active')
                                    <x-ui.status-badge status="active" label="Active" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" label="Inactive" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown align="end">
                                    <x-slot:extraActions>
                                        <a href="{{ $accRoute }}" class="btn btn-xs btn-soft-primary fw-bold text-nowrap px-2 py-1 fs-11" style="height: 28px; line-height: 20px; display: inline-flex; align-items: center;">
                                            {{ __('crm.view_account') }}
                                        </a>
                                    </x-slot:extraActions>

                                    @if (strtolower($customer->status) === 'active')
                                        <li>
                                            <a href="{{ route('crm.customers.toggleStatus', [$customer, 'status' => 'inactive']) }}" class="dropdown-item py-1.5 text-danger">
                                                <i class="feather-user-x me-2 text-danger"></i>{{ __('crm.mark_as_inactive') }}
                                            </a>
                                        </li>
                                    @else
                                        <li>
                                            <a href="{{ route('crm.customers.toggleStatus', [$customer, 'status' => 'active']) }}" class="dropdown-item py-1.5 text-success">
                                                <i class="feather-user-check me-2 text-success"></i>{{ __('crm.mark_as_active') }}
                                            </a>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="feather-users display-6 mb-2 text-muted opacity-50 d-block"></i>
                                {{ __('crm.no_customers_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 4. Common Component Pagination --}}
        <div class="mt-3">
            <x-ui.pagination 
                :currentPage="$customers->currentPage()" 
                :totalPages="$customers->lastPage()" 
                :totalResults="$customers->total()" 
                :perPage="$customers->perPage()" 
            />
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('selectAllCustomers')?.addEventListener('change', function() {
            document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = this.checked);
        });
    </script>
    @endpush
@endsection
