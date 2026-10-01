@extends('layouts.duralux')

@section('title', __('visitor.visitor_gate_pass') . ' - ' . $pass->pass_number . ' | SaaS ERP')
@section('page-title', __('visitor.visitor_gate_pass'))
@section('breadcrumb', __('visitor.visitor_management') . ' > ' . __('visitor.visitor_passes') . ' > ' . $pass->pass_number)

@push('styles')
<style>
    .badge-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
    }
    .badge-header {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: #ffffff;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        padding: 20px 24px;
    }
    .visitor-lg-avatar {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        object-fit: cover;
        border: 3px solid #f8fafc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }
    .visitor-lg-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 12px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
        color: #64748b;
        border: 3px solid #f8fafc;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    }
    .qr-box {
        background: #ffffff;
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        padding: 10px;
        display: inline-block;
        text-align: center;
    }

    @media print {
        body * {
            visibility: hidden;
        }
        #printableGatePass, #printableGatePass * {
            visibility: visible;
        }
        #printableGatePass {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 15px;
            box-shadow: none !important;
            border: 2px solid #000000 !important;
        }
        .no-print {
            display: none !important;
        }
    }
</style>
@endpush

@section('page-actions')
    <a href="{{ route('visitor.index') }}" class="btn btn-outline-secondary btn-sm me-2">
        <i class="feather-arrow-left me-1"></i> {{ __('visitor.back_to_list') }}
    </a>
    <button type="button" class="btn btn-primary btn-sm fw-semibold shadow-sm" onclick="window.print()">
        <i class="feather-printer me-1"></i> {{ __('visitor.print_pass') }}
    </button>
@endsection

@section('content')
<div class="container-fluid px-0">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center no-print" role="alert">
            <i class="feather-check-circle fs-18 text-success me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            <div class="card badge-card mb-4" id="printableGatePass">
                <!-- Header -->
                <div class="badge-header d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-primary px-3 py-1 text-uppercase fw-bold mb-1">{{ __('visitor.visitor_gate_pass') }}</span>
                        <h4 class="mb-0 text-white font-monospace">{{ $pass->pass_number }}</h4>
                    </div>
                    <div class="text-end">
                        @if($pass->status === 'Waiting Approval')
                            <span class="badge bg-warning text-dark fs-12 px-3 py-2">
                                <i class="feather-clock me-1"></i> {{ __('visitor.statuses.Waiting Approval') }}
                            </span>
                        @elseif($pass->status === 'Approved')
                            <span class="badge bg-success fs-12 px-3 py-2">
                                <i class="feather-check me-1"></i> {{ __('visitor.statuses.Approved') }}
                            </span>
                        @elseif($pass->status === 'Rejected')
                            <span class="badge bg-danger fs-12 px-3 py-2">
                                <i class="feather-x me-1"></i> {{ __('visitor.statuses.Rejected') }}
                            </span>
                        @elseif($pass->status === 'Checked-In')
                            <span class="badge bg-success fs-12 px-3 py-2">
                                <i class="feather-log-in me-1"></i> {{ __('visitor.statuses.Checked-In') }}
                            </span>
                        @elseif($pass->status === 'Checked-Out')
                            <span class="badge bg-secondary fs-12 px-3 py-2">
                                <i class="feather-log-out me-1"></i> {{ __('visitor.statuses.Checked-Out') }}
                            </span>
                        @else
                            <span class="badge bg-info text-white fs-12 px-3 py-2">
                                {{ __('visitor.statuses.' . $pass->status, [], null) ?? $pass->status }}
                            </span>
                        @endif
                    </div>
                </div>

                @if($pass->status === 'Waiting Approval')
                    <div class="alert alert-warning border-0 rounded-0 m-0 p-3 d-flex flex-wrap align-items-center justify-content-between gap-2 no-print">
                        <div class="d-flex align-items-center">
                            <i class="feather-alert-circle fs-18 me-2"></i>
                            <div>
                                <strong class="d-block text-dark">{{ __('visitor.pending_approvals') }}</strong>
                                <span class="fs-12 text-muted">{{ __('visitor.host_approval_pending') }}</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <form action="{{ route('visitor.approvals.approve', $pass->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success fw-semibold">
                                    <i class="feather-check me-1"></i> {{ __('visitor.approve') }}
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger fw-semibold" onclick="openRejectModalShow({{ $pass->id }})">
                                <i class="feather-x me-1"></i> {{ __('visitor.reject') }}
                            </button>
                        </div>
                    </div>
                @elseif($pass->status === 'Approved')
                    <div class="alert alert-success border-0 rounded-0 m-0 p-2.5 d-flex align-items-center no-print">
                        <i class="feather-check-circle fs-16 me-2 text-success"></i>
                        <span class="fs-12 text-success fw-semibold">{{ __('visitor.host_approved_notice') }}</span>
                    </div>
                @elseif($pass->status === 'Rejected')
                    <div class="alert alert-danger border-0 rounded-0 m-0 p-3 no-print">
                        <div class="d-flex align-items-center mb-1">
                            <i class="feather-x-circle fs-18 me-2 text-danger"></i>
                            <strong class="text-danger">{{ __('visitor.host_rejected_notice') }}</strong>
                        </div>
                        @if($pass->rejection_reason)
                            <div class="fs-12 ps-4 text-dark">
                                <strong>{{ __('visitor.rejection_reason') }}:</strong> {{ $pass->rejection_reason }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="card-body p-4">
                    <div class="row align-items-center mb-4 pb-3 border-bottom">
                        <div class="col-auto">
                            @if(!empty($pass->visitor->photo_url))
                                <img src="{{ $pass->visitor->photo_url }}" alt="{{ $pass->visitor->full_name }}" class="visitor-lg-avatar">
                            @else
                                <div class="visitor-lg-placeholder">
                                    {{ strtoupper(substr($pass->visitor->full_name ?? 'V', 0, 1)) }}
                                </div>
                            @endif
                        </div>
                        <div class="col">
                            <h3 class="fw-bold mb-1 text-dark">{{ $pass->visitor->full_name ?? 'N/A' }}</h3>
                            <p class="text-muted mb-1">
                                <i class="feather-phone me-1 text-primary"></i> {{ $pass->visitor->phone ?? 'N/A' }}
                                @if(!empty($pass->visitor->company_name))
                                    <span class="mx-2">•</span>
                                    <i class="feather-briefcase me-1 text-primary"></i> {{ $pass->visitor->company_name }}
                                @endif
                            </p>
                            @if(!empty($pass->visitor->designation))
                                <p class="text-muted small mb-0">{{ $pass->visitor->designation }}</p>
                            @endif
                        </div>
                        <div class="col-auto text-center">
                            <div class="qr-box p-2 bg-white rounded-3 border shadow-sm">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data={{ urlencode($pass->pass_number) }}" alt="QR Code" width="140" height="140" class="img-fluid rounded" style="display:block; margin: 0 auto; background: #fff;">
                                <div class="fs-13 fw-bold text-dark mt-1 font-monospace">{{ $pass->pass_number }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pass & Visit Metadata -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="text-muted small fw-semibold text-uppercase mb-1">{{ __('visitor.purpose_of_visit') }}</div>
                                <div class="fw-bold text-dark fs-15">{{ __('visitor.purposes.' . $pass->purpose, [], null) ?? $pass->purpose }}</div>
                                @if(!empty($pass->notes))
                                    <div class="small text-muted mt-1">{{ $pass->notes }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="text-muted small fw-semibold text-uppercase mb-1">{{ __('visitor.host_details') }}</div>
                                <div class="fw-bold text-dark fs-15">
                                    <i class="feather-user text-primary me-1"></i> {{ $pass->host->name ?? 'General Reception' }}
                                </div>
                                <div class="small text-muted">
                                    {{ $pass->host->email ?? '' }}
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-4">
                            <div class="p-3 border rounded-3 text-center">
                                <div class="text-muted small text-uppercase mb-1">{{ __('visitor.gate_number') }}</div>
                                <div class="fw-bold text-dark">{{ $pass->gate_number ?? 'Main Gate' }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 border rounded-3 text-center">
                                <div class="text-muted small text-uppercase mb-1">{{ __('visitor.check_in_time') }}</div>
                                <div class="fw-bold text-dark">
                                    {{ $pass->check_in_at ? \Carbon\Carbon::parse($pass->check_in_at)->format('d M, h:i A') : '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-3 border rounded-3 text-center">
                                <div class="text-muted small text-uppercase mb-1">{{ __('visitor.check_out_time') }}</div>
                                <div class="fw-bold text-dark">
                                    {{ $pass->check_out_at ? \Carbon\Carbon::parse($pass->check_out_at)->format('d M, h:i A') : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Charge -->
                    @if($pass->fee_amount > 0)
                        <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                            <span><strong>{{ __('visitor.fee_charge') }}:</strong></span>
                            <span class="fs-16 fw-bold">{{ format_currency($pass->fee_amount) }}</span>
                        </div>
                    @endif

                    <!-- Declared Belongings -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="feather-package me-1 text-primary"></i> {{ __('visitor.belongings') }}
                        </h6>
                        @if($pass->belongings && $pass->belongings->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('visitor.item_type') }}</th>
                                            <th>{{ __('visitor.serial_number') }}</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pass->belongings as $item)
                                            <tr>
                                                <td class="fw-semibold">{{ $item->item_type }}</td>
                                                <td class="font-monospace">{{ $item->serial_number ?? '—' }}</td>
                                                <td>{{ $item->notes ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0 fst-italic">{{ __('visitor.no_belongings') }}</p>
                        @endif
                    </div>

                    <!-- Safety Instructions -->
                    <div class="p-3 bg-light border-start border-3 border-warning rounded-2 mb-4">
                        <div class="fw-bold text-dark small mb-1">{{ __('visitor.safety_notice') }}</div>
                        <ul class="small text-muted mb-0 ps-3">
                            <li>{{ __('visitor.safety_point_1') }}</li>
                            <li>{{ __('visitor.safety_point_2') }}</li>
                            <li>{{ __('visitor.safety_point_3') }}</li>
                        </ul>
                    </div>

                    <!-- Signatures -->
                    <div class="row pt-4 text-center">
                        <div class="col-6">
                            <div class="border-bottom mx-auto mb-2" style="width: 160px; height: 35px;"></div>
                            <div class="small text-muted">{{ __('visitor.visitor_sign') }}</div>
                        </div>
                        <div class="col-6">
                            <div class="border-bottom mx-auto mb-2" style="width: 160px; height: 35px;"></div>
                            <div class="small text-muted">{{ __('visitor.authorized_sign') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center no-print">
                    <div class="small text-muted">
                        {{ __('visitor.entry_type') }}: <strong>{{ $pass->entry_type ?? 'Walk-in' }}</strong>
                    </div>
                    <div class="d-flex gap-2">
                        @if($pass->status === 'Expected' || $pass->status === 'Approved')
                            <form action="{{ route('visitor.passes.check-in', $pass->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="feather-log-in me-1"></i> {{ __('visitor.check_in') }}
                                </button>
                            </form>
                        @elseif($pass->status === 'Checked-In')
                            <form action="{{ route('visitor.passes.check-out', $pass->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="feather-log-out me-1"></i> {{ __('visitor.check_out') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Reject Modal for Show View -->
<div class="modal fade" id="rejectPassModalShow" tabindex="-1" aria-labelledby="rejectPassModalShowLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form id="rejectPassFormShow" method="POST" action="{{ route('visitor.approvals.reject', $pass->id) }}">
                @csrf
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h6 class="modal-title fw-bold" id="rejectPassModalShowLabel">
                        <i class="feather-alert-octagon me-2"></i> {{ __('visitor.reject_pass') }}
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="fs-13 text-dark mb-1">
                            Visitor: <strong>{{ $pass->visitor?->full_name }}</strong> ({{ $pass->pass_number }})
                        </p>
                        <p class="fs-12 text-muted">
                            {{ __('visitor.enter_rejection_reason') }}
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control fs-13" rows="3" placeholder="{{ __('visitor.enter_rejection_reason') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('crm.cancel') ?? 'Cancel' }}</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-semibold">
                        <i class="feather-x me-1"></i> {{ __('visitor.reject') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openRejectModalShow(passId) {
        const modal = new bootstrap.Modal(document.getElementById('rejectPassModalShow'));
        modal.show();
    }
</script>
@endpush
