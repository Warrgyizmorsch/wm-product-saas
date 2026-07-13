@extends('layouts.duralux')

@section('title', 'CRM Leads | SaaS ERP')
@section('page-title', 'CRM Leads')
@section('breadcrumb', 'CRM Leads')

@section('page-actions')
    <x-ui.button href="{{ route('crm.leads.create') }}" variant="primary" icon="feather-plus">
        Add New Call / Lead
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'call_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
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
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Leads Listing</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Call Date (Latest first)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Call Date (Oldest first)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Company Name (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Company Name (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Est. Sale (High to Low)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Est. Sale (Low to High)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('crm.leads.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search company, contact..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Priority</label>
                            <x-ui.odoo-form-ui type="select" name="priority">
                                <option value="">All Priorities</option>
                                <option value="Low" {{ request('priority') === 'Low' ? 'selected' : '' }}>Low</option>
                                <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>Medium</option>
                                <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>High</option>
                                <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>Urgent</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Segment</label>
                            <x-ui.odoo-form-ui type="select" name="segment">
                                <option value="">All Segments</option>
                                <option value="SME" {{ request('segment') === 'SME' ? 'selected' : '' }}>SME</option>
                                <option value="Mid-Market" {{ request('segment') === 'Mid-Market' ? 'selected' : '' }}>Mid-Market</option>
                                <option value="Enterprise" {{ request('segment') === 'Enterprise' ? 'selected' : '' }}>Enterprise</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="New" {{ request('status') === 'New' ? 'selected' : '' }}>New</option>
                                <option value="Follow-up Scheduled" {{ request('status') === 'Follow-up Scheduled' ? 'selected' : '' }}>Follow-up Scheduled</option>
                                <option value="Contacted" {{ request('status') === 'Contacted' ? 'selected' : '' }}>Contacted</option>
                                <option value="Qualified" {{ request('status') === 'Qualified' ? 'selected' : '' }}>Qualified</option>
                                <option value="Converted" {{ request('status') === 'Converted' ? 'selected' : '' }}>Converted</option>
                                <option value="Lost" {{ request('status') === 'Lost' ? 'selected' : '' }}>Lost</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>

                <!-- Action Dropdown for Import/Export/Download Sample (Action button style) -->
                <div class="dropdown d-inline-block">
                    <a href="javascript:void(0)" class="action-dropdown-btn dropdown-toggle-custom" title="Import / Export Options">
                        <i class="feather feather-paperclip"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end fs-13 shadow-lg">
                        <li>
                            <a href="{{ route('crm.leads.export') }}" class="dropdown-item">
                                <i class="feather-download me-2 text-muted fs-12"></i>Export Excel
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('crm.leads.downloadSample') }}" class="dropdown-item">
                                <i class="feather-file-text me-2 text-muted fs-12"></i>Download Sample
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importLeadsModal">
                                <i class="feather-upload me-2 text-muted fs-12"></i>Import
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Leads List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="leadsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Call Date & Time</th>
                        <th>Lead / Company</th>
                        <th>Phone / Email</th>
                        <th class="text-end">Value / Est. Sale</th>
                        <th>Source</th>
                        <th>Priority</th>
                        <th>Segment</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
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
                                <span class="fw-bold text-dark d-block mb-1">{{ $lead->company_name }}</span>
                                <span class="text-muted fs-11"><i class="feather-user me-1 fs-10 text-primary"></i>{{ $lead->contact_person ?: 'N/A' }}</span>
                            </td>
                            <td>
                                @if ($lead->phone)
                                    <span class="d-block text-dark"><i class="feather-phone fs-11 me-1 text-muted"></i>{{ $lead->phone }}</span>
                                @endif
                                @if ($lead->email)
                                    <span class="text-muted fs-11"><i class="feather-mail fs-11 me-1 text-muted"></i>{{ $lead->email }}</span>
                                @endif
                                @if (!$lead->phone && !$lead->email)
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-dark d-block mb-1">{{ $lead->expected_amount ? '₹' . number_format($lead->expected_amount, 2) : '—' }}</span>
                                @if($lead->expected_sale_date)
                                    <span class="text-muted fs-11"><i class="feather-calendar me-1 fs-10 text-success"></i>{{ $lead->expected_sale_date->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-muted fs-11">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-soft-secondary text-secondary">{{ $lead->source }}</span>
                            </td>
                            <td>
                                @if ($lead->priority == 'High' || $lead->priority == 'Urgent')
                                    <span class="badge bg-soft-danger text-danger">{{ $lead->priority }}</span>
                                @elseif ($lead->priority == 'Medium')
                                    <span class="badge bg-soft-warning text-warning">{{ $lead->priority }}</span>
                                @else
                                    <span class="badge bg-soft-success text-success">{{ $lead->priority }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-soft-info text-info">{{ $lead->segment }}</span>
                            </td>
                            <td>
                                @if ($lead->is_customer || $lead->status === 'Converted')
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i>Converted</span>
                                @else
                                    <div class="d-flex flex-column gap-1">
                                        <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="form-control status-select" data-select2-selector="status" style="width: 150px;">
                                                @foreach(['New', 'Follow-up Scheduled', 'Contacted', 'Qualified', 'Converted', 'Lost'] as $statusOption)
                                                    @php
                                                        $bgClass = 'bg-primary';
                                                        if($statusOption === 'Follow-up Scheduled') $bgClass = 'bg-warning';
                                                        elseif($statusOption === 'Contacted') $bgClass = 'bg-info';
                                                        elseif($statusOption === 'Qualified') $bgClass = 'bg-teal';
                                                        elseif($statusOption === 'Converted') $bgClass = 'bg-success';
                                                        elseif($statusOption === 'Lost') $bgClass = 'bg-danger';
                                                    @endphp
                                                    <option value="{{ $statusOption }}" data-bg="{{ $bgClass }}" {{ ($lead->status ?: 'New') === $statusOption ? 'selected' : '' }}>
                                                        {{ $statusOption }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                        @if (($lead->status ?: 'New') === 'Qualified' && $lead->getQuotations()->isEmpty())
                                            <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-primary mt-1 w-100 fw-bold py-1 px-1 fs-10">
                                                    <i class="feather-file-plus me-1"></i>Convert to Quotation
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                               <x-ui.action-dropdown :viewUrl="route('crm.leads.show', $lead->id)">
                           
                                   {{-- Edit --}}
                                   <li>
                                       <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="dropdown-item">
                                           <i class="feather-edit me-2 text-muted fs-12"></i>Edit Lead
                                       </a>
                                   </li>
                           
                                   {{-- Delete --}}
                                   <li><hr class="dropdown-divider"></li>
                                   <li>
                                       <form action="{{ route('crm.leads.destroy', $lead->id) }}"
                                             method="POST"
                                             onsubmit="return confirm('Are you sure you want to delete this lead?');">
                                           @csrf
                                           @method('DELETE')
                           
                                           <button type="submit" class="dropdown-item text-danger">
                                               <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Lead
                                           </button>
                                       </form>
                                   </li>
                           
                               </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-users fs-1 d-block mb-3 text-light"></i>
                                No leads registered yet. Click "Add New Call / Lead" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="c pt-3">
            <x-ui.pagination 
                :currentPage="$leads->currentPage()" 
                :totalPages="$leads->lastPage()" 
                :totalResults="$leads->total()" 
                :perPage="$leads->perPage()" />
        </div>
    </div>

    {{-- Import Leads Modal --}}
    <x-ui.modal id="importLeadsModal" title="Import Leads via Excel/CSV" submitText="Import File" :centered="true">
        <form method="POST" action="{{ route('crm.leads.import') }}" enctype="multipart/form-data" id="importLeadsForm">
            @csrf
            <p class="fs-13 text-muted mb-3">Upload an Excel (.xlsx, .xls) or CSV (.csv) file containing lead records. Make sure the headers match the column names in the sample template file.</p>
            <x-ui.odoo-form-ui type="file" name="file" label="Excel/CSV File" required placeholder="Choose file..." />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="importLeadsForm" class="btn btn-primary">Import File</button>
        </x-slot>
    </x-ui.modal>
@endsection

@push('styles')
    <!-- Select2 Theme Styles -->
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Make select2 container compact for table layout */
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 2px 8px;
            height: auto;
            font-size: 11px;
            font-weight: 600;
        }
        /* Ensure status dropdown inside table has a fixed minimum width */
        .status-select + .select2-container {
            min-width: 160px !important;
            width: 160px !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function () {
            // Auto submit status forms when changed in Select2
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });


            // Live Search filter for the Leads table
            $('#tableSearch').on('input', function() {
                var value = $(this).val().toLowerCase().trim();
                var visibleRows = 0;
                var totalRows = 0;

                $('#leadsTable tbody tr').each(function() {
                    // Skip the "No matching results" row if it exists
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

                // Remove existing "No matching results" row if it exists
                $('#leadsTable tbody tr.no-search-results').remove();

                // If no rows are visible and we have actual data rows, show a "No results found" row
                if (visibleRows === 0 && totalRows > 0) {
                    $('#leadsTable tbody').append(
                        '<tr class="no-search-results"><td colspan="9" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>No matching leads found for "' + value + '"</td></tr>'
                    );
                }
            });
        });
    </script>
@endpush
