@extends('layouts.duralux')

@section('title', 'Record Inspection Results | SaaS ERP')
@section('page-title', 'Quality Inspection Checklist')
@section('breadcrumb', 'Checklist details')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 900px;">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">Checklist #{{ $inspection->id }}</h4>
                <div class="text-muted fs-12">Quality Plan Template: <strong>{{ $inspection->plan->name }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-2 text-uppercase">{{ $inspection->stage }}</span>
            </div>
        </div>

        {{-- Record parameters values form --}}
        @if($inspection->status === 'draft')
            <form method="POST" action="{{ route('production.quality.inspections.results', $inspection->id) }}">
                @csrf
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Parameter Parameter</th>
                                <th>Expected Tolerance / Specification</th>
                                <th>Recorded Result Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inspection->results as $index => $res)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $res->parameter->name }}</div>
                                        <input type="hidden" name="results[{{ $index }}][parameter_id]" value="{{ $res->parameter->id }}">
                                    </td>
                                    <td>
                                        @if($res->parameter->type === 'numeric')
                                            Range: {{ $res->parameter->min_value }} — {{ $res->parameter->max_value }} ({{ $res->parameter->unit_of_measure }})
                                        @elseif($res->parameter->type === 'pass_fail')
                                            Pass / Fail check
                                        @else
                                            Visual text comments
                                        @endif
                                    </td>
                                    <td>
                                        @if($res->parameter->type === 'numeric')
                                            <input type="number" step="0.01" name="results[{{ $index }}][value_numeric]" class="form-control form-control-sm" required>
                                        @elseif($res->parameter->type === 'pass_fail')
                                            <select name="results[{{ $index }}][value_pass]" class="form-select form-select-sm" required>
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
                    </table>
                </div>

                <button type="submit" class="btn btn-warning w-100">Submit Measurements checklist</button>
            </form>
        @else
            {{-- Submitted/Approved read-only view --}}
            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Parameter</th>
                            <th>Target / Tolerance</th>
                            <th>Recorded Value</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inspection->results as $res)
                            <tr>
                                <td class="fw-bold">{{ $res->parameter->name }}</td>
                                <td>
                                    @if($res->parameter->type === 'numeric')
                                        Range: {{ $res->parameter->min_value }} — {{ $res->parameter->max_value }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($res->parameter->type === 'numeric')
                                        {{ $res->recorded_value_numeric }}
                                    @elseif($res->parameter->type === 'pass_fail')
                                        {{ $res->recorded_value_pass ? 'PASS' : 'FAIL' }}
                                    @else
                                        {{ $res->recorded_value_text }}
                                    @endif
                                </td>
                                <td class="{{ $res->result === 'passed' ? 'text-success fw-bold' : 'text-danger fw-bold' }}">
                                    {{ strtoupper($res->result) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Audit approval section --}}
            @if($inspection->status === 'submitted')
                <div class="border p-4 rounded bg-light mb-4">
                    <h6 class="fw-bold text-dark mb-3">E-Signature Auditor Verification & Sign-off</h6>
                    <form method="POST" action="{{ route('production.quality.inspections.approve', $inspection->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-muted">Auditor Verification Signature Hash</label>
                            <input type="text" name="esignature" class="form-control font-monospace" placeholder="Enter Signer Pin / Authentication Token" required>
                        </div>
                        <button type="submit" class="btn btn-success">Approve, Audited and Lock Result</button>
                    </form>
                </div>
            @else
                <div class="alert alert-success d-flex align-items-center">
                    <i class="feather-check-circle fs-20 me-3"></i>
                    <div>
                        <strong>Approved and Closed</strong>. Audited by User ID: {{ $inspection->audited_by }} at {{ $inspection->audited_at }}.
                        <br><span class="font-monospace text-muted fs-11">Sig: {{ $inspection->esignature }}</span>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
