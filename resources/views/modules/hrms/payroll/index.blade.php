@extends('layouts.duralux')

@section('title', 'Payroll Operations | SaaS ERP')
@section('page-title', 'Payroll Operations')
@section('breadcrumb', 'HRMS / Payroll Runs')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-play" data-bs-toggle="modal" data-bs-target="#initiateRunModal">
        Process New Month
    </x-ui.button>
@endsection

@section('content')
<style>
    .run-item {
        border-left: 4px solid transparent !important;
        transition: all 0.15s ease-in-out;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .run-item.active {
        background-color: #f1f5f9 !important;
        border-left-color: var(--bs-primary) !important;
    }
    .run-item:hover:not(.active) {
        background-color: #f8fafc !important;
    }
</style>

<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
    <div class="row g-4">
        <!-- LEFT: Runs History List -->
        <div class="col-md-3 col-12 border-end pe-4">
            <div class="pb-2.5 mb-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="feather-calendar me-2 text-primary"></i>Payroll Runs</h6>
            </div>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                @forelse($runs as $run)
                    @php $isActive = $selectedRun && $selectedRun->id === $run->id; @endphp
                    <a href="{{ route('hrms.payroll.index', ['run_id' => $run->id]) }}" 
                       class="list-group-item list-group-item-action py-3 px-4 run-item {{ $isActive ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-14">
                                {{ Carbon\Carbon::parse($run->payroll_month . '-01')->format('F Y') }}
                            </span>
                            @if($run->status === 'draft')
                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-10">Draft</span>
                            @elseif($run->status === 'locked')
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-10">Locked</span>
                            @else
                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-10">Paid</span>
                            @endif
                        </div>
                        <div class="fs-11 text-muted">
                            {{ $run->start_date->format('d M') }} - {{ $run->end_date->format('d M Y') }}
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                        <span>No payroll runs initiated.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Active Run Register -->
        <div class="col-md-9 col-12 ps-4">
            @if($selectedRun)
                <div>
                    <!-- Cycle Metadata & Payout Actions -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-4 gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                Payroll Register &mdash; {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}
                            </h5>
                            <div class="text-muted fs-12">
                                Cycle dates: {{ $selectedRun->start_date->format('F d, Y') }} to {{ $selectedRun->end_date->format('F d, Y') }}
                            </div>
                        </div>
                        <div>
                            @if($selectedRun->status === 'draft')
                                <form action="{{ route('hrms.payroll.run.lock', $selectedRun->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning text-white fw-bold"><i class="feather-lock me-2"></i>Lock Payroll Register</button>
                                </form>
                            @elseif($selectedRun->status === 'locked')
                                <form action="{{ route('hrms.payroll.run.release', $selectedRun->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold"><i class="feather-check-circle me-2"></i>Release Payouts</button>
                                </form>
                            @else
                                <button class="btn btn-outline-success fw-bold border-2" disabled><i class="feather-check me-2"></i>Payout Completed</button>
                            @endif
                        </div>
                    </div>

                    <!-- Calculations Register Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light fs-11 text-uppercase tracking-wider">
                                <tr>
                                    <th>Employee Details</th>
                                    <th class="text-center">LOP Days</th>
                                    <th class="text-end">Base Gross</th>
                                    <th class="text-end">Ad-hoc Earnings</th>
                                    <th class="text-end">Ad-hoc Deductions</th>
                                    <th class="text-end">Retro Refunds</th>
                                    <th class="text-end">Penalties</th>
                                    <th class="text-end">Net Payout</th>
                                    <th class="text-center">Payout Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registerData as $row)
                                    @php $summary = $row['calc']; @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $row['employee']->full_name }}</div>
                                            <span class="fs-12 text-muted">{{ $row['employee']->employee_id }} &bull; {{ $row['employee']->job_title }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ ($summary['lop_days'] ?? 0) > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} px-2 py-1">
                                                {{ $summary['lop_days'] ?? 0 }} days
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">₹{{ number_format($summary['base_gross_earnings'] ?? 0, 2) }}</td>
                                        <td class="text-end text-success">₹{{ number_format($summary['adhoc_earnings'] ?? 0, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($summary['adhoc_deductions'] ?? 0, 2) }}</td>
                                        <td class="text-end text-primary">₹{{ number_format($summary['retro_lop_reversals'] ?? 0, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($summary['attendance_penalties'] ?? 0, 2) }}</td>
                                        <td class="text-end fw-bold text-primary">₹{{ number_format($summary['net_payout'] ?? 0, 2) }}</td>
                                        <td class="text-center">
                                            @if($row['is_held'])
                                                <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">Withheld</span>
                                            @else
                                                <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">Approved</span>
                                            @endif

                                            @if($selectedRun->status === 'draft')
                                                <form action="{{ route('hrms.payroll.hold.toggle', [$row['employee']->id, $selectedRun->payroll_month]) }}" method="POST" class="d-inline ms-1">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs {{ $row['is_held'] ? 'btn-soft-success' : 'btn-soft-danger' }} rounded-circle py-0.5 px-1.5 fs-10" title="{{ $row['is_held'] ? 'Release' : 'Hold' }}">
                                                        <i class="feather-{{ $row['is_held'] ? 'play' : 'pause' }}"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="p-5 text-center">
                    <i class="feather-info fs-36 text-secondary mb-3"></i>
                    <h6 class="fw-bold text-dark">No Active Payroll Run</h6>
                    <p class="text-muted fs-13 mb-0">Select an existing cycle from the history panel or click "Process New Month" to begin calculations.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- INITIATE RUN MODAL -->
<div class="modal fade" id="initiateRunModal" tabindex="-1" aria-labelledby="initiateRunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="initiateRunModalLabel"><i class="feather-play me-2 text-primary"></i>Initiate Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Payroll Month (YYYY-MM)" name="payroll_month" :required="true" placeholder="e.g. 2026-08" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" subtype="date" label="Start Date" name="start_date" :required="true" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" subtype="date" label="End Date" name="end_date" :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white">Start Calculations</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById('initiateRunModal');
        if (modal) {
            document.body.appendChild(modal);
        }
    });
</script>
@endpush
