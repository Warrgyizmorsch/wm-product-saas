@extends('layouts.duralux')

@section('title', __('purchase.rfqs') . ' | SaaS ERP')
@section('page-title', __('purchase.rfqs'))
@section('breadcrumb')
    {{ __('purchase.rfqs') }}
@endsection

@section('page-actions')
    <x-ui.button href="{{ route('purchase.rfqs.create') }}" variant="primary" icon="feather-plus">
        {{ __('purchase.new_rfq') }}
    </x-ui.button>
@endsection

@section('content')

    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = ($currency === 'INR') ? '₹' : $currency . ' ';
        $sortBy = request('sort_by', 'rfq_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel text-dark">
        <!-- Toast Notifications -->

        <x-ui.odoo-form-ui type="sheet">

            <!-- TOP SUMMARY KPI CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.stat-widget 
                        :title="__('purchase.total_filtered_rfqs')" 
                        :value="$totalFilteredCount" 
                        :subtitle="__('purchase.all_matching_records')" 
                        icon="feather-layers" 
                        color="primary" 
                        variant="compact" />
                </div>
                <div class="col-md-4">
                    <x-ui.stat-widget 
                        :title="__('purchase.filtered_spend')" 
                        :value="$currencySymbol . number_format($totalFilteredSpend, 2)" 
                        :subtitle="__('purchase.total_rfq_purchase_value')" 
                        icon="feather-shopping-bag" 
                        color="info" 
                        variant="compact" />
                </div>
                <div class="col-md-4">
                    <x-ui.stat-widget 
                        :title="__('purchase.total_savings_achieved')" 
                        :value="'+' . $currencySymbol . number_format($totalFilteredSavings, 2)" 
                        :subtitle="__('purchase.net_saved_across_all') . ' ' . $totalFilteredCount . ' RFQs'" 
                        icon="feather-trending-up" 
                        color="success" 
                        variant="compact" />
                </div>
            </div>

            <!-- Toolbar: Search, Sort, Filters -->
            <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-3">{{ __('purchase.rfqs_list') }}</h5>
                
                <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
                    <!-- Quick Search (HRMS Common Component Style) -->
                    <form method="GET" action="{{ route('purchase.rfqs.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="{{ __('purchase.search_rfq_placeholder') }}" 
                            value="{{ request('search') }}"
                            style="box-shadow: none; height: 32px; width: 220px;"
                        >
                    </form>

                    <!-- Custom Sort Component -->
                    <x-ui.sort-dropdown :label="__('crm.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'rfq_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('purchase.rfq_date_latest') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'rfq_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('purchase.rfq_date_oldest') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'rfq_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('purchase.rfq_number_az') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Custom Filter Component -->
                    <form method="GET" action="{{ route('purchase.rfqs.index') }}" class="d-inline">
                        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>
                            
                            <div class="mb-2">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">{{ __('purchase.all_statuses') }}</option>
                                    <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>{{ __('purchase.status_draft') }}</option>
                                    <option value="Sent" {{ request('status') === 'Sent' ? 'selected' : '' }}>{{ __('purchase.status_sent') }}</option>
                                    <option value="Received" {{ request('status') === 'Received' ? 'selected' : '' }}>{{ __('purchase.status_received') }}</option>
                                    <option value="Confirmed" {{ request('status') === 'Confirmed' ? 'selected' : '' }}>{{ __('purchase.status_confirmed') }}</option>
                                    <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>{{ __('purchase.status_cancelled') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            @if(isset($isAdmin) && $isAdmin)
                                <div class="mb-2">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.purchaser') }}</label>
                                    <select name="created_by" class="form-select form-select-sm">
                                        <option value="">{{ __('purchase.all_purchasers') }}</option>
                                        @foreach($allPurchasers as $u)
                                            <option value="{{ $u->id }}" {{ request('created_by') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-2">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.from_date') }}</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.to_date') }}</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('purchase.rfqs.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>

            <!-- RFQs List Table -->
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="rfqsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th>{{ __('purchase.rfq_number') }}</th>
                            <th>{{ __('purchase.purchaser') }}</th>
                            <th>{{ __('purchase.vendors_suppliers') }}</th>
                            <th>{{ __('purchase.rfq_date') }}</th>
                            <th>{{ __('purchase.linked_requisition') }}</th>
                            <th class="text-end">{{ __('purchase.savings_achieved') }}</th>
                            <th class="text-center">{{ __('purchase.status') }}</th>
                            <th class="text-end pe-4">{{ __('purchase.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse($rfqs as $rfq)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <a href="{{ route('purchase.rfqs.show', $rfq->id) }}" class="fw-bold text-primary text-decoration-none">
                                        <i class="feather-file-text text-primary me-1 fs-12"></i>{{ $rfq->rfq_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-user me-1 text-muted fs-11"></i>{{ $rfq->creator?->name ?? 'System' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @foreach($rfq->rfqVendors as $rv)
                                            <div class="fw-semibold text-dark fs-12 d-flex align-items-center">
                                                <i class="feather-truck text-muted me-1 fs-11"></i>{{ $rv->vendor?->name ?? '—' }}
                                                @if($rv->status === 'Received')
                                                    <span class="badge bg-soft-success text-success fw-bold ms-2 px-1.5 py-0.5 rounded" style="font-size: 9px; letter-spacing: 0.3px; text-transform: uppercase;">
                                                        <i class="feather-check me-0.5" style="font-size: 8px;"></i>{{ __('purchase.status_quoted') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted fs-12">
                                        <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $rfq->rfq_date ? $rfq->rfq_date->format('d M Y') : '—' }}
                                    </span>
                                </td>
                                <td>
                                    @if($rfq->requisition)
                                        <a href="{{ route('purchase.requisitions.show', $rfq->purchase_requisition_id) }}" class="badge bg-soft-primary text-primary font-monospace fw-semibold text-decoration-none px-2 py-1">
                                            <i class="feather-link-2 me-1 fs-10"></i>{{ $rfq->requisition->requisition_number }}
                                        </a>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace">
                                    @if(isset($rfq->savings_amount) && $rfq->savings_amount > 0)
                                        <span class="badge bg-soft-success text-success font-monospace fs-11 px-2 py-1 border border-success-subtle">
                                            +{{ $currencySymbol }}{{ number_format($rfq->savings_amount, 2) }} ({{ $rfq->savings_percent }}%)
                                        </span>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge :status="$rfq->status" size="sm" />
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.action-dropdown :viewUrl="route('purchase.rfqs.show', $rfq->id)">
                                        @if($rfq->status === 'Draft')
                                            <li>
                                                <a href="{{ route('purchase.rfqs.edit', $rfq->id) }}" class="dropdown-item">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('purchase.edit_rfq') }}
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('purchase.rfqs.send', $rfq->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather-mail me-2 text-muted fs-12"></i>{{ __('purchase.send_rfq_vendors') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif

                                        @if($rfq->status === 'Received')
                                            <li>
                                                <form action="{{ route('purchase.rfqs.confirm', $rfq->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="feather-check-circle me-2 text-muted fs-12"></i>{{ __('purchase.confirm_finalize') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif

                                        @if(in_array($rfq->status, ['Draft', 'Sent']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('purchase.rfqs.destroy', $rfq->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('purchase.confirm_delete_rfq') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('purchase.delete') }}
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
                                    <i class="feather-list fs-1 d-block mb-3 text-light"></i>
                                    {{ __('purchase.no_rfqs') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

            <div class="c pt-3">
                <x-ui.pagination 
                    :currentPage="$rfqs->currentPage()" 
                    :totalPages="$rfqs->lastPage()" 
                    :totalResults="$rfqs->total()" 
                    :perPage="$rfqs->perPage()" />
            </div>

        </x-ui.odoo-form-ui>
    </div>
@endsection
