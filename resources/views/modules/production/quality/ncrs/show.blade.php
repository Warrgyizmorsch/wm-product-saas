@extends('layouts.duralux')

@section('title', 'NCR Details & Disposition | SaaS ERP')
@section('page-title', 'NCR Assessment & Verification')
@section('breadcrumb', 'NCR details')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 900px;">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h4 class="fw-bold text-danger mb-1">{{ $ncr->ncr_number }}</h4>
                <div class="text-muted fs-12">Category: <strong class="text-capitalize">{{ $ncr->category }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-danger text-danger px-3 py-2 text-uppercase">{{ $ncr->status }}</span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="fw-bold text-dark mb-2">Description & Investigation Info</h6>
                <p class="text-muted fs-13 border p-3 rounded bg-light">{{ $ncr->description }}</p>

                <ul class="list-group list-group-flush fs-12 mt-3">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Linked Quality Inspection:</span>
                        <strong>{{ $ncr->quality_inspection_id ? '#' . $ncr->quality_inspection_id : 'Manual Log' }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Production Order:</span>
                        <strong>{{ $ncr->production_order_id ? 'Order #' . $ncr->production_order_id : '—' }}</strong>
                    </li>
                </ul>
            </div>

            {{-- Disposition workflow panel --}}
            <div class="col-md-6">
                <div class="card border border-warning shadow-sm">
                    <div class="card-header bg-warning-subtle py-2">
                        <span class="fw-bold text-warning-emphasis">Quality Disposition Actions</span>
                    </div>
                    <div class="card-body">
                        @if($ncr->status === 'open' || $ncr->status === 'under_review')
                            <form method="POST" action="{{ route('production.quality.ncrs.disposition', $ncr->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted">Select Disposition Strategy</label>
                                    <select name="disposition_type" class="form-select" required>
                                        <option value="rework">Generate Rework Order (RWK)</option>
                                        <option value="scrap">Scrap & Disposal Log</option>
                                        <option value="use_as_is">Accept Deviation (Use As-Is)</option>
                                    </select>
                                </div>

                                {{-- Hidden defaults for easy service integration --}}
                                <input type="hidden" name="original_production_order_id" value="{{ $ncr->production_order_id }}">
                                <input type="hidden" name="work_center_id" value="{{ $workCenters->first()->id ?? 1 }}">
                                <input type="hidden" name="category" value="finished_good">
                                <input type="hidden" name="reason_code" value="defect">
                                <input type="hidden" name="quantity" value="10">
                                <input type="hidden" name="cost" value="150">

                                <button type="submit" class="btn btn-warning w-100">Apply Quality Disposition</button>
                            </form>
                        @else
                            <div class="alert alert-info">
                                Disposition finalized as: <strong>{{ strtoupper($ncr->disposition_type) }}</strong>
                                @if($ncr->reworkOrder)
                                    <br>Rework Order: <a href="{{ route('production.rework.show', $ncr->reworkOrder->id) }}">{{ $ncr->reworkOrder->rework_number }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Verification e-signature closure --}}
        @if($ncr->status === 'disposition')
            <div class="border p-4 rounded bg-light-subtle mb-4 border-success">
                <h6 class="fw-bold text-success mb-3">Verification & Closure Verification</h6>
                <form method="POST" action="{{ route('production.quality.ncrs.close', $ncr->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-muted">Digital E-Signature Token</label>
                        <input type="text" name="esignature" class="form-control" placeholder="Enter Authentication PIN to close NCR" required>
                    </div>
                    <button type="submit" class="btn btn-success">Verify, Approve, and Close NCR</button>
                </form>
            </div>
        @elseif($ncr->status === 'closed')
            <div class="alert alert-success d-flex align-items-center">
                <i class="feather-check-circle fs-20 me-3"></i>
                <div>
                    <strong>Closed and Verified</strong>. Closed by User ID: {{ $ncr->closed_by }} at {{ $ncr->closed_at }}.
                    <br><span class="font-monospace text-muted fs-11">Sig: {{ $ncr->esignature_closed }}</span>
                </div>
            </div>
        @endif
    </div>
@endsection
