@extends('layouts.duralux')

@section('title', 'CRM Leads | SaaS ERP')
@section('page-title', 'CRM Leads')
@section('breadcrumb', 'CRM Leads')

@section('page-actions')
    <span class="badge bg-soft-success text-success p-2 me-2">
        <i class="feather-database me-1"></i> Database Connected
    </span>
    <a href="{{ route('crm.leads.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Add New Call / Lead
    </a>
@endsection

@section('content')
    <!-- Success Message -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Error Message -->
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <ul class="fs-12 mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="row g-4 mb-4">
        <!-- Metric 1: Total Leads -->
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Total Calls / Leads</span>
                            <h3 class="mb-1 mt-2 text-dark fw-bolder">{{ $metrics['total'] }}</h3>
                            <span class="text-primary fs-12 fw-semibold">
                                <i class="feather-phone-call me-1"></i> Active CRM database
                            </span>
                        </div>
                        <div class="avatar-text avatar-lg bg-soft-primary text-primary">
                            <i class="feather-phone"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 2: Expected Revenue -->
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Expected Revenue</span>
                            <h3 class="mb-1 mt-2 text-dark fw-bolder">₹{{ number_format($metrics['revenue'], 2) }}</h3>
                            <span class="text-success fs-12 fw-semibold">
                                <i class="feather-trending-up me-1"></i> Projected sales value
                            </span>
                        </div>
                        <div class="avatar-text avatar-lg bg-soft-success text-success">
                            <i class="feather-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 3: High Priority Leads -->
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">High Priority</span>
                            <h3 class="mb-1 mt-2 text-dark fw-bolder">{{ $metrics['high_priority'] }}</h3>
                            <span class="text-danger fs-12 fw-semibold">
                                <i class="feather-alert-circle me-1"></i> Requires urgent follow-up
                            </span>
                        </div>
                        <div class="avatar-text avatar-lg bg-soft-danger text-danger">
                            <i class="feather-alert-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric 4: Enterprise Segment -->
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-bold">Enterprise Segment</span>
                            <h3 class="mb-1 mt-2 text-dark fw-bolder">{{ $metrics['enterprise'] }}</h3>
                            <span class="text-info fs-12 fw-semibold">
                                <i class="feather-briefcase me-1"></i> High-value accounts
                            </span>
                        </div>
                        <div class="avatar-text avatar-lg bg-soft-info text-info">
                            <i class="feather-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leads Table List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 py-3">
            <h5 class="card-title mb-0 fw-bold">Leads Listing</h5>
            <div class="card-header-action">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-0"><i class="feather-search text-muted"></i></span>
                    <input type="text" id="tableSearch" class="form-control bg-light border-0" placeholder="Search leads...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="leadsTable">
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">Call Date & Time</th>
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
                    <tbody class="fs-13">
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="ps-4">
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
                                    <div class="hstack gap-2 justify-content-end">
                                        <!-- View Details -->
                                        <a href="{{ route('crm.leads.show', $lead->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Details">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        
                                        <!-- Edit -->
                                        <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="avatar-text avatar-md bg-soft-info text-info" data-bs-toggle="tooltip" title="Edit">
                                            <i class="feather feather-edit-3"></i>
                                        </a>
         
                                        <!-- Delete -->
                                        <form id="delete-form-{{ $lead->id }}" action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-danger text-danger" onclick="if(confirm('Are you sure you want to delete this lead?')) { document.getElementById('delete-form-{{ $lead->id }}').submit(); }" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather feather-trash-2"></i>
                                            </a>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-users fs-1 d-block mb-3 text-light"></i>
                                    No leads registered yet. Click "Add New Call / Lead" to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
