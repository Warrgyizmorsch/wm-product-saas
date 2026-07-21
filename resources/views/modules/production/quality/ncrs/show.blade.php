@extends('layouts.duralux')

@section('title', 'NCR Details & Disposition | SaaS ERP')
@section('page-title', 'NCR Assessment & Verification')
@section('breadcrumb', 'NCR Details')

@section('page-actions')
    <a href="{{ route('production.ncrs.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        {{-- Detail Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">Non-Conformance Report: {{ $ncr->ncr_number }}</h4>
                <div class="text-muted fs-12">Category: <strong class="text-capitalize text-dark">{{ $ncr->category }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-danger text-danger px-3 py-1.5 rounded-pill text-uppercase">{{ $ncr->status }}</span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Left Column --}}
            <div class="col-md-6 border-end">
                <h5 class="fw-bold text-dark mb-3">Defect Investigation Details</h5>
                
                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Linked Inspection:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if($ncr->quality_inspection_id)
                                <a href="{{ route('production.inspections.show', $ncr->quality_inspection_id) }}" class="text-primary">
                                    Checklist #{{ $ncr->quality_inspection_id }}
                                </a>
                            @else
                                Manual Log
                            @endif
                        </span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Production Order:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if($ncr->production_order_id)
                                <a href="{{ route('production.orders.show', $ncr->production_order_id) }}" class="text-primary">
                                    Order #{{ $ncr->production_order_id }}
                                </a>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Reported Date:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">{{ $ncr->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                </div>

                <div class="mt-4 bg-light p-3 rounded border border-dashed">
                    <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-2">Detailed Defect Description</span>
                    <p class="mb-0 text-dark fs-13 text-justify">{{ $ncr->description }}</p>
                </div>
            </div>

            {{-- Right Column: Disposition Workflow --}}
            <div class="col-md-6">
                <h5 class="fw-bold text-dark mb-3">Quality Disposition Actions</h5>

                <div class="card border border-warning shadow-sm">
                    <div class="card-header bg-warning-subtle py-2">
                        <span class="fw-bold text-warning-emphasis fs-13">Apply Quality Action Strategy</span>
                    </div>
                    <div class="card-body">
                        @if($ncr->status === 'open' || $ncr->status === 'under_review')
                            <form method="POST" action="{{ route('production.quality.ncrs.disposition', $ncr->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted fw-semibold fs-12">Select Disposition Strategy</label>
                                    <select id="disposition_type_select" name="disposition_type" class="form-select form-select-sm" required>
                                        <option value="rework">Generate Rework Order (RWK)</option>
                                        <option value="scrap">Scrap &amp; Disposal Log</option>
                                        <option value="use_as_is">Accept Deviation (Use As-Is)</option>
                                    </select>
                                </div>

                                <input type="hidden" name="original_production_order_id" value="{{ $ncr->production_order_id }}">

                                {{-- Rework specific inputs --}}
                                <div id="rework_fields">
                                    <div class="mb-3">
                                        <label class="form-label text-muted fw-semibold fs-12">Rework Work Center</label>
                                        <select name="work_center_id" class="form-select form-select-sm">
                                            @foreach($workCenters as $wc)
                                                <option value="{{ $wc->id }}">{{ $wc->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Quantity to Rework</label>
                                            <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="1" step="any">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Rework Cost Est. ($)</label>
                                            <input type="number" name="cost_estimate" class="form-control form-control-sm" value="150" min="0" step="any">
                                        </div>
                                    </div>
                                </div>

                                {{-- Scrap specific inputs --}}
                                <div id="scrap_fields" style="display: none;">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Quantity to Scrap</label>
                                            <input type="number" id="scrap_qty_input" class="form-control form-control-sm" value="1" min="0.0001" step="any">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Disposal Cost ({{ active_currency_symbol() }})</label>
                                            <input type="number" name="cost" class="form-control form-control-sm" value="50" min="0" step="any">
                                        </div>

                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Category</label>
                                            <select name="category" class="form-select form-select-sm">
                                                <option value="finished_good" selected>Finished Good</option>
                                                <option value="wip_part">WIP Part</option>
                                                <option value="raw_material">Raw Material</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted fw-semibold fs-12">Reason Code</label>
                                            <input type="text" name="reason_code" class="form-control form-control-sm" value="defect">
                                        </div>
                                    </div>
                                </div>

                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const dispSelect = document.getElementById('disposition_type_select');
                                        const reworkFields = document.getElementById('rework_fields');
                                        const scrapFields = document.getElementById('scrap_fields');
                                        const scrapQtyInput = document.getElementById('scrap_qty_input');

                                        function toggleFields() {
                                            const type = dispSelect.value;
                                            if (type === 'rework') {
                                                reworkFields.style.display = 'block';
                                                scrapFields.style.display = 'none';
                                                // Make sure rework's quantity input is named "quantity"
                                                reworkFields.querySelector('input[name="quantity"]').disabled = false;
                                                scrapQtyInput.removeAttribute('name');
                                            } else if (type === 'scrap') {
                                                reworkFields.style.display = 'none';
                                                scrapFields.style.display = 'block';
                                                // Disable rework quantity and make scrap quantity named "quantity"
                                                reworkFields.querySelector('input[name="quantity"]').disabled = true;
                                                scrapQtyInput.setAttribute('name', 'quantity');
                                            } else {
                                                reworkFields.style.display = 'none';
                                                scrapFields.style.display = 'none';
                                                reworkFields.querySelector('input[name="quantity"]').disabled = true;
                                                scrapQtyInput.removeAttribute('name');
                                            }
                                        }

                                        dispSelect.addEventListener('change', toggleFields);
                                        toggleFields(); // Run initial state check
                                    });
                                </script>

                                <button type="submit" class="btn btn-warning btn-sm w-100">
                                    <i class="feather-tool me-1"></i>Apply Quality Disposition
                                </button>
                            </form>
                        @else
                            <div class="alert alert-info py-2 px-3 mb-0 fs-13">
                                Disposition finalized as: <strong class="text-uppercase text-dark">{{ $ncr->disposition_type }}</strong>
                                @if($ncr->reworkOrder)
                                    <br class="mb-1">Rework Order: <a href="{{ route('production.rework.show', $ncr->reworkOrder->id) }}" class="fw-bold text-primary">{{ $ncr->reworkOrder->rework_number }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Verification E-Signature Closure --}}
                <div class="mt-4">
                    @if($ncr->status === 'disposition')
                        <div class="border p-3 rounded bg-light border-success">
                            <h6 class="fw-bold text-success mb-3"><i class="feather-check-circle me-1"></i>Verification & Closure Sign-off</h6>
                            <form method="POST" action="{{ route('production.quality.ncrs.close', $ncr->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted fs-12 fw-semibold">Digital E-Signature Token / Verification PIN</label>
                                    <input type="text" name="esignature" class="form-control form-control-sm" placeholder="Enter authentication PIN to close NCR" required>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">Verify, Approve, and Close NCR</button>
                            </form>
                        </div>
                    @elseif($ncr->status === 'closed')
                        <div class="alert alert-success d-flex align-items-center mb-0 py-3">
                            <i class="feather-check-circle fs-20 me-3"></i>
                            <div>
                                <strong class="d-block mb-1">Closed and Verified</strong>
                                <span class="fs-12 text-success-800">Closed by Auditor (User ID: {{ $ncr->closed_by }}) at {{ $ncr->closed_at }}</span>
                                <br><span class="font-monospace text-muted fs-10">Signature Pin: {{ $ncr->esignature_closed }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
