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
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Contact Phone/Email</th>
                            <th class="text-end">Expected Amount</th>
                            <th>Sale Date</th>
                            <th>Source</th>
                            <th>Priority</th>
                            <th>Segment</th>
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
                                    <span class="fw-bold text-dark">{{ $lead->company_name }}</span>
                                </td>
                                <td>
                                    <span>{{ $lead->contact_person ?: 'N/A' }}</span>
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
                                <td class="text-end fw-bold text-dark">
                                    {{ $lead->expected_amount ? '₹' . number_format($lead->expected_amount, 2) : '—' }}
                                </td>
                                <td>
                                    <span>{{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-soft-secondary text-secondary">{{ $lead->source }}</span>
                                </td>
                                <td>
                                    @if ($lead->priority == 'High' || $lead->priority == 'Urgent')
                                        <span class="badge bg-soft-danger text-danger"><i class="feather-arrow-up-right me-1 fs-10"></i>{{ $lead->priority }}</span>
                                    @elseif ($lead->priority == 'Medium')
                                        <span class="badge bg-soft-warning text-warning"><i class="feather-minus me-1 fs-10"></i>{{ $lead->priority }}</span>
                                    @else
                                        <span class="badge bg-soft-success text-success"><i class="feather-arrow-down-left me-1 fs-10"></i>{{ $lead->priority }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-info">{{ $lead->segment }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="hstack gap-2 justify-content-end">
                                        <!-- View Requirement (static eye icon) -->
                                        <a href="javascript:void(0)" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Requirement">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                        
                                        <!-- Edit -->
                                        <a href="{{ route('crm.leads.edit', $lead->id) }}" class="avatar-text avatar-md bg-soft-info text-info" data-bs-toggle="tooltip" title="Edit">
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
                                <td colspan="10" class="text-center py-5 text-muted">
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

@push('scripts')
    <script>
        $(function () {
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
                        '<tr class="no-search-results"><td colspan="10" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>No matching leads found for "' + value + '"</td></tr>'
                    );
                }
            });
        });
    </script>
@endpush
