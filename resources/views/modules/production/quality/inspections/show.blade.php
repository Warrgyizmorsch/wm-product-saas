@extends('layouts.duralux')

@section('title', 'Record Inspection Results | SaaS ERP')
@section('page-title', 'Quality Inspection Checklist')
@section('breadcrumb', 'Checklist Details')

@section('page-actions')
    <a href="{{ route('production.inspections.index') }}" class="btn btn-secondary me-2">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white">

        {{-- Subcontract Operational Linkage Card --}}
        @php
            $subOp = $inspection->productionOrderOperation;
            $subMo = $inspection->productionOrder ?? $subOp?->order;
            $vendor = $subOp?->vendor;
        @endphp
        @if($subOp && $subOp->is_external)
            <div class="card border border-info bg-soft-info p-3 mb-4 rounded-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <span class="badge bg-info text-white font-monospace fs-10 mb-1">Subcontract Vendor Receipt Inspection</span>
                        <h6 class="fw-bold text-dark mb-1">
                            Vendor: <span class="text-primary">{{ $vendor?->name ?? 'Subcontract Vendor' }}</span>
                            @if($subMo)
                                | MO: <a href="{{ route('production.orders.show', $subMo->id) }}" class="fw-bold text-primary">{{ $subMo->order_number }}</a>
                            @endif
                            | Op: <span class="font-monospace fw-bold">{{ $subOp->operation_number }} — {{ $subOp->name }}</span>
                        </h6>
                        <div class="fs-12 text-muted">
                            Sample Inspected: <strong>{{ number_format($inspection->sample_size ?? 0, 0) }} units</strong> | 
                            Passed: <strong class="text-success">{{ number_format($inspection->passed_qty ?? 0, 0) }}</strong> | 
                            Failed/Rejected: <strong class="text-danger">{{ number_format($inspection->failed_qty ?? 0, 0) }}</strong>
                        </div>
                    </div>
                    @if($subMo)
                        <a href="{{ route('production.orders.show', $subMo->id) }}" class="btn btn-sm btn-outline-primary shadow-sm"><i class="feather-external-link me-1"></i>View Production Order</a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Detail Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">Inspection Checklist #{{ $inspection->id }}</h4>
                <div class="text-muted fs-12">Quality Plan Template: <strong class="text-dark">{{ $inspection->plan?->name ?? 'Default Plan' }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-1.5 rounded-pill text-uppercase">{{ $inspection->stage }}</span>
            </div>
        </div>

        {{-- Record parameters values form --}}
        @if($inspection->status === 'draft')
            <form method="POST" action="{{ route('production.quality.inspections.results', $inspection->id) }}">
                @csrf
                <div class="table-responsive mb-4">
                    <x-ui.odoo-form-ui type="table">
                        <thead>
                            <tr>
                                <th style="width: 35%">Inspection Parameter</th>
                                <th style="width: 30%">Expected Tolerance / Specification</th>
                                <th style="width: 35%">Recorded Result Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inspection->results as $index => $res)
                                <tr>
                                    <td class="align-middle">
                                        <div class="fw-bold text-dark fs-13">{{ $res->parameter->name }}</div>
                                        <input type="hidden" name="results[{{ $index }}][parameter_id]" value="{{ $res->parameter->id }}">
                                    </td>
                                    <td class="align-middle text-muted fs-12">
                                        @if($res->parameter->type === 'numeric')
                                            Range: <strong class="text-dark">{{ $res->parameter->min_value }} — {{ $res->parameter->max_value }}</strong> ({{ $res->parameter->unit_of_measure }})
                                        @elseif($res->parameter->type === 'pass_fail')
                                            Pass / Fail check
                                        @else
                                            Visual text comments
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($res->parameter->type === 'numeric')
                                            <input type="number" step="0.01" name="results[{{ $index }}][value_numeric]" class="form-control form-control-sm font-monospace" style="max-width: 200px;" required>
                                        @elseif($res->parameter->type === 'pass_fail')
                                            <select name="results[{{ $index }}][value_pass]" class="form-select form-select-sm" style="max-width: 200px;" required>
                                                <option value="1">PASS</option>
                                                <option value="0">FAIL</option>
                                            </select>
                                        @else
                                            <input type="text" name="results[{{ $index }}][value_text]" class="form-control form-control-sm" required>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                    <a href="{{ route('production.inspections.index') }}" class="btn btn-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-warning px-4">
                        <i class="feather-check-square me-2"></i>Submit Measurements Checklist
                    </button>
                </div>
            </form>
        @else
            {{-- Submitted/Approved read-only view --}}
            <div class="table-responsive mb-4">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 30%">Inspection Parameter</th>
                            <th style="width: 30%">Target / Tolerance</th>
                            <th style="width: 25%">Recorded Value</th>
                            <th style="width: 15%">Result Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inspection->results as $res)
                            <tr>
                                <td class="fw-bold align-middle text-dark fs-13">{{ $res->parameter->name }}</td>
                                <td class="align-middle text-muted fs-12">
                                    @if($res->parameter->type === 'numeric')
                                        Range: {{ $res->parameter->min_value }} — {{ $res->parameter->max_value }} ({{ $res->parameter->unit_of_measure }})
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="align-middle fw-semibold fs-13">
                                    @if($res->parameter->type === 'numeric')
                                        {{ $res->recorded_value_numeric }}
                                    @elseif($res->parameter->type === 'pass_fail')
                                        <span class="badge {{ $res->recorded_value_pass ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger' }}">
                                            {{ $res->recorded_value_pass ? 'PASS' : 'FAIL' }}
                                        </span>
                                    @else
                                        {{ $res->recorded_value_text }}
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if($res->result === 'passed')
                                        <span class="text-success fw-bold fs-12 text-uppercase"><i class="feather-check-circle me-1"></i>Passed</span>
                                    @else
                                        <span class="text-danger fw-bold fs-12 text-uppercase"><i class="feather-x-circle me-1"></i>Failed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

            {{-- Audit approval section --}}
            <div class="mt-4">
                @if($inspection->status === 'submitted')
                    <div class="border p-3 rounded bg-light border-warning">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-lock me-1"></i>Auditor Verification & Sign-off</h6>
                        <form method="POST" action="{{ route('production.quality.inspections.approve', $inspection->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label text-muted fs-12 fw-semibold">Auditor Verification Signature Hash / Token</label>
                                <input type="text" name="esignature" class="form-control form-control-sm font-monospace" placeholder="Enter Signer Pin / Authentication Token" required>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm w-100">Approve, Audit and Lock Result</button>
                        </form>
                    </div>
                @else
                    <div class="alert alert-success d-flex align-items-center mb-0 py-3">
                        <i class="feather-check-circle fs-20 me-3"></i>
                        <div>
                            <strong class="d-block mb-1">Approved and Locked</strong>
                            <span class="fs-12 text-success-800 d-block mb-1">Audited by user ID: {{ $inspection->audited_by }} at {{ $inspection->audited_at }}</span>
                            <span class="font-monospace text-muted fs-10">Signature Pin: {{ $inspection->esignature }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
