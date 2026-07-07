@extends('layouts.duralux')

@section('title', 'CAPA Details & RCA | SaaS ERP')
@section('page-title', 'CAPA Core Investigation')
@section('breadcrumb', 'CAPA details')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 900px;">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="danger" title="{{ session('error') }}" />
        @endif

        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-1">{{ $capa->capa_number }}</h4>
                <div class="text-muted fs-12">Assignee: <strong>{{ $capa->owner->name ?? '—' }}</strong> | Target Date: <strong>{{ $capa->target_date ? $capa->target_date->toDateString() : '—' }}</strong></div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-2 text-uppercase">{{ $capa->status }}</span>
            </div>
        </div>

        {{-- RCA Section (5 Whys and Fishbone) --}}
        @if($capa->status === 'draft')
            <div class="card border border-primary shadow-sm mb-4">
                <div class="card-header bg-primary-subtle py-2">
                    <span class="fw-bold text-primary-emphasis">Root Cause Analysis (5 Whys & Fishbone)</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('production.quality.capas.rca', $capa->id) }}">
                        @csrf
                        
                        <h6 class="fw-bold text-dark mb-2">5 Whys Analysis</h6>
                        @for($i = 1; $i <= 5; $i++)
                            <div class="mb-2">
                                <label class="form-label fs-11 text-muted mb-0">Why {{ $i }}?</label>
                                <input type="text" name="five_whys[]" class="form-control form-control-sm" placeholder="Reason {{ $i }}" required>
                            </div>
                        @endfor

                        <h6 class="fw-bold text-dark mt-3 mb-2">Fishbone (Ishikawa) Categories</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-0">Method</label>
                                <input type="text" name="fishbone[method]" class="form-control form-control-sm" placeholder="Method defects" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-0">Machine</label>
                                <input type="text" name="fishbone[machine]" class="form-control form-control-sm" placeholder="Machine defects" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-0">Man</label>
                                <input type="text" name="fishbone[man]" class="form-control form-control-sm" placeholder="Operator errors" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-sm btn-primary mt-3 w-100">Record Root Cause Analysis</button>
                    </form>
                </div>
            </div>
        @else
            <div class="card border border-light shadow-sm mb-4 bg-light">
                <div class="card-header bg-light py-2 fw-bold text-dark">Root Cause Analysis Records</div>
                <div class="card-body">
                    <h6 class="fw-bold text-dark fs-12">5 Whys Answers:</h6>
                    <ol class="fs-12 text-muted">
                        @foreach(($capa->rca_analysis_json['five_whys'] ?? []) as $why)
                            <li>{{ $why }}</li>
                        @endforeach
                    </ol>

                    <h6 class="fw-bold text-dark fs-12 mt-3">Fishbone Analysis:</h6>
                    <ul class="fs-12 text-muted">
                        <li>Method: {{ $capa->rca_analysis_json['fishbone']['method'] ?? '—' }}</li>
                        <li>Machine: {{ $capa->rca_analysis_json['fishbone']['machine'] ?? '—' }}</li>
                        <li>Man: {{ $capa->rca_analysis_json['fishbone']['man'] ?? '—' }}</li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Verification e-signature closure --}}
        @if($capa->status === 'active')
            <div class="border p-4 rounded border-success bg-success-subtle mb-4">
                <h6 class="fw-bold text-success mb-3">Verify Effectiveness & Close CAPA</h6>
                <form method="POST" action="{{ route('production.quality.capas.close', $capa->id) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold">Effectiveness Review Comments</label>
                        <textarea name="effectiveness_review" class="form-control" rows="2" placeholder="Record verification and audit review notes..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold">E-Signature Token</label>
                        <input type="text" name="esignature" class="form-control" placeholder="Enter Sign-off Authentication Signature" required>
                    </div>
                    <button type="submit" class="btn btn-success">Approve, Close and Archive CAPA</button>
                </form>
            </div>
        @elseif($capa->status === 'closed')
            <div class="alert alert-success d-flex align-items-center">
                <i class="feather-check-circle fs-20 me-3"></i>
                <div>
                    <strong>Closed and Verified</strong>. Review comments: <em>{{ $capa->effectiveness_review }}</em>.
                    <br><span class="font-monospace text-muted fs-11">Sig: {{ $capa->esignature_closed }}</span>
                </div>
            </div>
        @endif
    </div>
@endsection
