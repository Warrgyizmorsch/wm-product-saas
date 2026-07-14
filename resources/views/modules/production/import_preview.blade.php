@extends('layouts.duralux')

@section('title', 'Import Preview | SaaS ERP')
@section('page-title', 'Master Data Import Preview')
@section('breadcrumb', 'Import Preview')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Summary Card -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">Previewing: {{ ucwords(str_replace('-', ' ', $type)) }}</h4>
                        <p class="text-muted mb-0 fs-13">Review parsed records and errors before confirming database transaction.</p>
                    </div>
                    @php
                        $cancelRoute = match ($type) {
                            'work-centers' => 'production.work-centers.index',
                            'machines' => 'production.machines.index',
                            'boms' => 'production.boms.index',
                            'routings' => 'production.routing.index',
                            default => 'production.work-centers.index',
                        };
                    @endphp
                    <a href="{{ route($cancelRoute) }}" class="btn btn-light border btn-sm">
                        <i class="feather-arrow-left me-1"></i>Cancel & Return
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 text-center bg-light">
                                <h3 class="fw-bold text-dark mb-0">{{ count($previewRows) }}</h3>
                                <small class="text-muted">Total Rows Parsed</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 text-center bg-soft-success">
                                <h3 class="fw-bold text-success mb-0">{{ count($previewRows) - $errorCount }}</h3>
                                <small class="text-success-800">Valid / Ready Rows</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 text-center bg-soft-danger">
                                <h3 class="fw-bold text-danger mb-0">{{ $errorCount }}</h3>
                                <small class="text-danger-800">Rows with Errors</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 text-center bg-soft-warning">
                                <h3 class="fw-bold text-warning mb-0">{{ $errorCount > 0 ? 'Validation Failed' : 'Ready' }}</h3>
                                <small class="text-warning-800">File Status</small>
                            </div>
                        </div>
                    </div>

                    @if($errorCount > 0)
                        <div class="alert alert-warning border-0 shadow-sm mt-4 mb-0 d-flex align-items-center">
                            <i class="feather-alert-triangle fs-4 me-3 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Attention: Validation Errors Detected</h6>
                                <p class="mb-0 fs-13">The file contains invalid data rows. You can either import only the valid rows, or correct the file and upload again.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Import Action Form -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body py-4">
                    <form action="{{ route('production.import-export.import-confirm', $type) }}" method="POST" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark fs-13">Duplicate Handling Strategy</label>
                            <select name="strategy" class="form-select fs-13" required>
                                <option value="create">Skip Existing Records (Create Only)</option>
                                <option value="update">Overwrite / Update Matching Records</option>
                            </select>
                            <small class="text-muted d-block mt-1">Duplicates are identified using unique business keys (e.g. Codes, BOM Numbers, Routing Numbers).</small>
                        </div>
                        <div class="col-md-8 text-md-end">
                            <a href="{{ route($cancelRoute) }}" class="btn btn-light border me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary" @disabled((count($previewRows) - $errorCount) <= 0)>
                                <i class="feather-check-circle me-1"></i>Confirm Import ({{ count($previewRows) - $errorCount }} valid rows)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Parsed Rows Details Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold text-dark mb-0">Row-by-Row Import Validation Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 8%" class="ps-4">Line / Row</th>
                                    <th style="width: 25%">Business Identifier</th>
                                    <th style="width: 15%">Validation Status</th>
                                    <th style="width: 52%">Error Details / ready status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewRows as $row)
                                    <tr class="{{ $row['valid'] ? '' : 'table-soft-danger' }}">
                                        <td class="ps-4 fw-bold text-muted">#{{ $row['row_number'] }}</td>
                                        <td class="fw-semibold text-dark">{{ $row['key'] }}</td>
                                        <td>
                                            @if($row['valid'])
                                                <span class="badge bg-soft-success text-success px-2 py-1"><i class="feather-check me-1"></i>Valid</span>
                                            @else
                                                <span class="badge bg-soft-danger text-danger px-2 py-1"><i class="feather-x me-1"></i>Invalid</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row['valid'])
                                                <span class="text-success fw-semibold"><i class="feather-info me-1"></i>Ready for database transaction.</span>
                                            @else
                                                <ul class="text-danger mb-0 ps-3">
                                                    @foreach($row['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .bg-soft-success {
        background-color: rgba(46, 202, 106, 0.15) !important;
    }
    .bg-soft-danger {
        background-color: rgba(220, 53, 69, 0.15) !important;
    }
    .bg-soft-warning {
        background-color: rgba(255, 193, 7, 0.15) !important;
    }
    .text-success-800 {
        color: #1e7e34;
    }
    .text-danger-800 {
        color: #bd2130;
    }
    .text-warning-800 {
        color: #d39e00;
    }
    .table-soft-danger {
        background-color: rgba(220, 53, 69, 0.03);
    }
</style>
@endsection
