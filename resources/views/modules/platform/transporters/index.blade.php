@extends('layouts.duralux')

@section('title', __('crm.transporters_directory') . ' | SaaS ERP')
@section('page-title', __('crm.transporters_directory'))
@section('breadcrumb', __('crm.logistics_transporters_breadcrumb'))

@section('page-actions')
    <x-ui.button href="{{ route('platform.transporters.create') }}" variant="primary" icon="feather-plus">
        {{ __('crm.new_transporter') }}
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $activeStatus = request('status');
    @endphp

    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fs-13 py-2.5 mb-3" role="alert">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- 1. Header & Actions Toolbar --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i>{{ __('crm.transporters_listing') }}</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box -->
                <form method="GET" action="{{ route('platform.transporters.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 260px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_transporter_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('platform.transporters.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="{{ __('crm.reset') }}">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('crm.sort') ?: 'SORT'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.latest_created') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.oldest_created') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.transporter_name_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.transporter_name_za') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('platform.transporters.index') }}" class="d-inline">
                    @foreach(request()->except(['status', 'search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <x-ui.filter :label="__('crm.filter') ?: 'FILTER'" offset="0, 5">
                        <div class="p-3 style-filter-menu" style="min-width: 250px;">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_transporter_placeholder')" value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.transporter_status_label') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="all" @selected(request('status', 'all') === 'all')>{{ __('crm.all_transporters') }}</option>
                                    <option value="active" @selected(request('status') === 'active')>{{ __('crm.active_only') }}</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('crm.inactive_only') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="{{ route('platform.transporters.index') }}" class="btn btn-xs btn-light">{{ __('crm.reset') }}</a>
                                <x-ui.button type="submit" variant="primary" size="xs">{{ __('crm.apply_filters') }}</x-ui.button>
                            </div>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Common ERP Odoo Table Component --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="transportersTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllTransporters">
                        </th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.transporter_name_col') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.transporter_id_col') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.gstin_pan_col') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.phone_mobile_col') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.city_state_col') }}</th>
                        <th style="background-color: #e8ecf1 !important;">{{ __('crm.status') }}</th>
                        <th style="width: 8%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transporters as $transporter)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input transporter-checkbox">
                            </td>
                            <td>
                                <div>
                                    <a href="{{ route('platform.transporters.show', $transporter) }}" class="fw-bold text-dark hover-primary text-decoration-none d-block">
                                        {{ $transporter->name }}
                                    </a>
                                    <div class="fs-11 text-muted">
                                        @if($transporter->code)
                                            <span class="font-monospace text-primary fw-semibold">Code: {{ $transporter->code }}</span>
                                        @endif
                                        @if($transporter->email)
                                            <span class="ms-1"><i class="feather-mail me-1"></i>{{ $transporter->email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($transporter->transporter_id)
                                    <span class="badge bg-light text-dark font-monospace border px-2 py-1 fs-11">{{ $transporter->transporter_id }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->gstin)
                                    <span class="font-monospace text-uppercase fs-12 text-primary fw-bold">{{ $transporter->gstin }}</span>
                                @elseif($transporter->pan_number)
                                    <span class="font-monospace text-uppercase fs-12 text-dark">{{ $transporter->pan_number }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->phone)
                                    <span class="text-dark"><i class="feather-phone me-1 text-muted fs-11"></i>{{ $transporter->phone }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($transporter->city || $transporter->state)
                                    <span>{{ implode(', ', array_filter([$transporter->city, $transporter->state])) }}</span>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if(strtolower($transporter->status) === 'active')
                                    <x-ui.status-badge status="active" :label="__('crm.active')" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" :label="__('crm.inactive')" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('platform.transporters.show', $transporter)">
                                    <li>
                                        <a href="{{ route('platform.transporters.show', $transporter) }}" class="dropdown-item fs-12 py-1.5">
                                            <i class="feather-eye me-2 text-primary"></i>{{ __('crm.view_360_profile') }}
                                        </a>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item fs-12 py-1.5" data-bs-toggle="modal" data-bs-target="#editTransporterModal{{ $transporter->id }}">
                                            <i class="feather-edit-2 me-2 text-info"></i>{{ __('crm.edit_quick_master') }}
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <form method="POST" action="{{ route('platform.transporters.destroy', $transporter) }}" onsubmit="return confirm('{{ __('crm.confirm_delete_transporter') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item fs-12 py-1.5 text-danger">
                                                <i class="feather-trash-2 me-2"></i>{{ __('crm.delete_transporter') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>

                                <!-- Edit Modal Component for each row -->
                                <x-ui.modal id="editTransporterModal{{ $transporter->id }}" :title="__('crm.edit_transporter_master')" size="md" :centered="true" :formAction="route('platform.transporters.update', $transporter)" formMethod="PUT" :submitText="__('crm.save_changes')" :closeText="__('crm.cancel')">
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_name_field')" name="name" :value="$transporter->name" :required="true" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_master_code')" name="code" :value="$transporter->code" placeholder="e.g. TRP-001" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_id_eway')" name="transporter_id" :value="$transporter->transporter_id" placeholder="Optional 15-digit ID" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_number_field')" name="gstin" :value="$transporter->gstin" placeholder="Optional 15-digit GSTIN" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" :label="__('crm.phone_mobile_col')" name="phone" :value="$transporter->phone" placeholder="Mobile / Landline" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.official_email_address')" name="email" :value="$transporter->email" placeholder="email@domain.com" />
                                    </div>
                                    <div class="mb-3">
                                        <x-ui.odoo-form-ui type="select" :label="__('crm.active_status')" name="status" :required="true" :searchable="false">
                                            <option value="active" @selected($transporter->status === 'active')>{{ __('crm.active') }}</option>
                                            <option value="inactive" @selected($transporter->status === 'inactive')>{{ __('crm.inactive') }}</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-truck fs-32 d-block mb-2 text-muted"></i>
                                {{ __('crm.no_transporters_found') }}
                                <div class="mt-2">
                                    <a href="{{ route('platform.transporters.create') }}" class="btn btn-sm btn-primary">
                                        <i class="feather-plus me-1"></i>{{ __('crm.new_transporter_master') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 3. Common ERP Pagination Component --}}
        @if($transporters->hasPages())
            <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fs-12 text-muted">{{ __('crm.showing_transporters', ['from' => $transporters->firstItem(), 'to' => $transporters->lastItem(), 'total' => $transporters->total()]) }}</span>
                {{ $transporters->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllTransporters');
        const checkboxes = document.querySelectorAll('.transporter-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }
    });
</script>
@endpush
