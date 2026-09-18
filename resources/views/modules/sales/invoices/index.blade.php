@extends('layouts.duralux')

@section('title', __('crm.invoices') . ' | SaaS ERP')
@section('page-title', __('crm.invoices'))
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.invoices'))

@section('page-actions')
    <x-ui.button href="{{ route('sales.invoices.create') }}" variant="primary" icon="feather-plus">
        {{ __('crm.create_invoice') }}
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
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

        <!-- Toolbar: Title, Sort, Filter Drawer -->
        <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">{{ __('crm.all_customer_invoices') }}</h5>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.latest_invoices_first') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.oldest_invoices_first') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'invoice_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'invoice_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.invoice_number_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'invoice_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'invoice_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.invoice_number_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.highest_amount_first') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.lowest_amount_first') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('sales.invoices.index') }}" class="d-inline">
                    <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_invoice_ph')" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.invoice_status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('crm.all_statuses') }}</option>
                                <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>{{ __('crm.status_draft') }}</option>
                                <option value="Sent" {{ request('status') === 'Sent' ? 'selected' : '' }}>{{ __('crm.status_sent') }}</option>
                                <option value="Partially Paid" {{ request('status') === 'Partially Paid' ? 'selected' : '' }}>{{ __('crm.status_partially_paid') }}</option>
                                <option value="Paid" {{ request('status') === 'Paid' ? 'selected' : '' }}>{{ __('crm.status_paid') }}</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>{{ __('crm.status_cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('sales.invoices.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>

                <div class="dropdown d-inline-block">
                    <a href="javascript:void(0)" class="action-dropdown-btn" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('crm.options') }}">
                        <i class="feather-paperclip"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end fs-13 shadow-lg">
                        <li>
                            <a href="{{ route('sales.payments.index') }}" class="dropdown-item">
                                <i class="feather-dollar-sign me-2 text-muted fs-12"></i>{{ __('crm.customer_receipts') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Invoices List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="invoicesTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                        </th>
                        <th>{{ __('crm.invoice_num_date') }}</th>
                        <th>{{ __('crm.reference_do_so') }}</th>
                        <th>{{ __('crm.customer') }}</th>
                        <th class="text-end">{{ __('crm.total_amount') }}</th>
                        <th>{{ __('crm.status') }}</th>
                        <th>{{ __('crm.einvoice_status') }}</th>
                        <th>{{ __('crm.eway_bill_status') }}</th>
                        <th class="text-end pe-4">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        @php
                            $badgeClass = 'bg-soft-secondary text-secondary';
                            if ($inv->status == 'Paid') $badgeClass = 'bg-soft-success text-success';
                            elseif ($inv->status == 'Partially Paid') $badgeClass = 'bg-soft-warning text-warning';
                            elseif ($inv->status == 'Sent') $badgeClass = 'bg-soft-info text-info';
                            elseif ($inv->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';

                            $custName = $inv->customer?->name ?? $inv->salesOrder?->customer?->name ?? '—';
                            $custPhone = $inv->customer?->phone ?? $inv->salesOrder?->customer?->phone;

                            $statusLabel = match($inv->status) {
                                'Draft' => __('crm.status_draft'),
                                'Sent' => __('crm.status_sent'),
                                'Partially Paid' => __('crm.status_partially_paid'),
                                'Paid' => __('crm.status_paid'),
                                'Cancelled' => __('crm.status_cancelled'),
                                default => $inv->status
                            };
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $inv->id }}">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-text avatar-sm bg-soft-primary text-primary me-2">
                                        <i class="feather-file-text"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('sales.invoices.show', $inv->id) }}" class="fw-bold text-primary d-block">
                                            {{ $inv->invoice_number }}
                                        </a>
                                        <span class="text-muted fs-11">
                                            <i class="feather-calendar me-1 fs-10"></i>{{ $inv->invoice_date ? date('d/m/Y', strtotime($inv->invoice_date)) : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($inv->salesOrder)
                                    <a href="{{ route('sales.orders.show', $inv->sales_order_id) }}" class="text-muted fw-semibold d-block">
                                        {{ $inv->salesOrder->sales_order_number }}
                                    </a>
                                @endif
                                @if ($inv->dispatchOrder)
                                    <span class="badge bg-light text-dark font-monospace border fs-10">DO: {{ $inv->dispatchOrder->dispatch_number }}</span>
                                @endif
                                @if(!$inv->salesOrder && !$inv->dispatchOrder)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-dark d-block mb-0.5">{{ $custName }}</span>
                                @if ($custPhone)
                                    <span class="text-muted fs-11"><i class="feather-phone me-1 fs-10"></i>{{ $custPhone }}</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-dark pe-3">
                                {{ format_currency($inv->total_amount) }}
                                @if ($inv->balance_due > 0 && $inv->balance_due < $inv->total_amount)
                                    <small class="d-block text-danger fs-10 font-normal">{{ __('crm.due') }}: {{ format_currency($inv->balance_due) }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fs-11 fw-bold">{{ $statusLabel }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-warning text-warning border border-warning fs-11 fw-semibold px-2 py-1"><i class="feather-clock me-1"></i>{{ __('crm.pending') }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-warning text-warning border border-warning fs-11 fw-semibold px-2 py-1"><i class="feather-clock me-1"></i>{{ __('crm.pending') }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="hstack gap-2 justify-content-end">
                                    @if ($inv->status === 'Draft')
                                        <form action="{{ route('sales.invoices.send', $inv->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="action-dropdown-btn" title="{{ __('crm.mark_as_sent') }}" data-bs-toggle="tooltip">
                                                <i class="feather-send fs-13"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <x-ui.action-dropdown :viewUrl="route('sales.invoices.show', $inv->id)">
                                        @if ($inv->status === 'Draft')
                                            <li>
                                                <form action="{{ route('sales.invoices.send', $inv->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item fw-semibold border-0 bg-transparent w-100 text-start">
                                                        <i class="feather-send me-2 text-muted fs-12"></i>{{ __('crm.mark_as_sent') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                        @if ($inv->status !== 'Paid' && $inv->status !== 'Cancelled')
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a href="{{ route('sales.payments.create', ['invoice_id' => $inv->id, 'customer_id' => $inv->customer_id ?: $inv->salesOrder?->customer_id]) }}" class="dropdown-item text-success fw-semibold">
                                                    <i class="feather-dollar-sign me-2 text-success fs-12"></i>{{ __('crm.record_payment') }}
                                                </a>
                                            </li>
                                        @endif
                                    </x-ui.action-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-file-text fs-1 d-block mb-3 text-light"></i>
                                {{ __('crm.no_invoices_generated_yet') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$invoices->currentPage()" 
                :totalPages="$invoices->lastPage()" 
                :totalResults="$invoices->total()" 
                :perPage="$invoices->perPage()" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // Select all checkbox functionality
            $('#selectAllCheckbox').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).is(':checked'));
            });

            // Live Search filter for the Invoices table
            $('#tableSearch').on('input', function() {
                var value = $(this).val().toLowerCase().trim();
                var visibleRows = 0;
                var totalRows = 0;

                $('#invoicesTable tbody tr').each(function() {
                    if ($(this).hasClass('no-search-results')) return;
                    totalRows++;
                    var rowText = $(this).text().toLowerCase();
                    if (rowText.indexOf(value) > -1) {
                        $(this).show();
                        visibleRows++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#invoicesTable tbody tr.no-search-results').remove();

                if (visibleRows === 0 && totalRows > 0) {
                    $('#invoicesTable tbody').append(
                        '<tr class="no-search-results"><td colspan="7" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>No invoices matching "' + value + '"</td></tr>'
                    );
                }
            });
        });
    </script>
@endpush
