@extends('layouts.duralux')

@section('title', 'CAPA Details & RCA | SaaS ERP')
@section('page-title', 'CAPA Core Investigation')
@section('breadcrumb', 'CAPA Details')

@section('page-actions')
    <a href="{{ route('production.capas.index') }}" class="btn btn-secondary me-2">
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
                <h4 class="fw-bold text-dark mb-1">Corrective & Preventive Action: {{ $capa->capa_number }}</h4>
                <div class="text-muted fs-12">
                    Action Owner: <strong class="text-dark">{{ $capa->owner->name ?? '—' }}</strong> | 
                    Target Date: <strong class="text-dark">{{ $capa->target_date ? $capa->target_date->toDateString() : '—' }}</strong>
                </div>
            </div>
            <div>
                <span class="badge bg-soft-primary text-primary px-3 py-1.5 rounded-pill text-uppercase">{{ $capa->status }}</span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Left Column: Root Cause Details & Analysis --}}
            <div class="col-md-6 border-end">
                <h5 class="fw-bold text-dark mb-3">Investigation Context & Plans</h5>

                <div class="row erp-form-row mb-2">
                    <div class="col-md-4">
                        <span class="fw-semibold text-muted fs-13">Linked NCR Reference:</span>
                    </div>
                    <div class="col-md-8">
                        <span class="text-dark fw-bold fs-13">
                            @if($capa->ncr)
                                <a href="{{ route('production.ncrs.show', $capa->ncr->id) }}" class="text-primary">
                                    {{ $capa->ncr->ncr_number }}
                                </a>
                            @else
                                None / General Investigation
                            @endif
                        </span>
                    </div>
                </div>

                <div class="mt-3 bg-light p-3 rounded border border-dashed mb-3">
                    <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-1.5">Corrective Action Plan</span>
                    <p class="mb-0 text-dark fs-13 text-justify">{{ $capa->corrective_action }}</p>
                </div>

                <div class="bg-light p-3 rounded border border-dashed">
                    <span class="fw-semibold text-muted d-block fs-11 text-uppercase mb-1.5">Preventive Action Plan</span>
                    <p class="mb-0 text-dark fs-13 text-justify">{{ $capa->preventive_action ?: 'No preventive action plan specified.' }}</p>
                </div>
            </div>

            {{-- Right Column: RCA and Closure --}}
            <div class="col-md-6">
                <h5 class="fw-bold text-dark mb-3">Root Cause Analysis (RCA)</h5>

                @if($capa->status === 'draft')
                    <div class="card border border-primary shadow-sm mb-4">
                        <div class="card-header bg-primary-subtle py-2">
                            <span class="fw-bold text-primary-emphasis fs-13">Perform 5 Whys & Fishbone Analysis</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('production.quality.capas.rca', $capa->id) }}">
                                @csrf
                                
                                <h6 class="fw-bold text-dark mb-2 fs-12">5 Whys Investigation</h6>
                                @for($i = 1; $i <= 5; $i++)
                                    <div class="mb-2">
                                        <label class="form-label fs-11 text-muted mb-0">Why {{ $i }}?</label>
                                        <input type="text" name="five_whys[]" class="form-control form-control-sm" placeholder="Reason {{ $i }}" required>
                                    </div>
                                @endfor

                                <h6 class="fw-bold text-dark mt-3 mb-2 fs-12">Fishbone (Ishikawa) Root Causes</h6>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label fs-11 text-muted mb-0">Method</label>
                                        <input type="text" name="fishbone[method]" class="form-control form-control-sm" placeholder="Method checks" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fs-11 text-muted mb-0">Machine</label>
                                        <input type="text" name="fishbone[machine]" class="form-control form-control-sm" placeholder="Equipment status" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fs-11 text-muted mb-0">Man</label>
                                        <input type="text" name="fishbone[man]" class="form-control form-control-sm" placeholder="Human error" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary mt-3 w-100">
                                    <i class="feather-check-circle me-1"></i>Record Root Cause Analysis
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="card border border-light shadow-sm mb-4 bg-light">
                        <div class="card-header bg-light py-2 fw-bold text-dark fs-13">Root Cause Analysis Records</div>
                        <div class="card-body py-2.5">
                            <h6 class="fw-bold text-dark fs-12 mb-1.5">5 Whys Answers:</h6>
                            <ol class="fs-12 text-muted mb-3 ps-3">
                                @foreach(($capa->rca_analysis_json['five_whys'] ?? []) as $why)
                                    <li class="mb-1">{{ $why }}</li>
                                @endforeach
                            </ol>

                            <h6 class="fw-bold text-dark fs-12 mb-1.5">Fishbone Factors:</h6>
                            <ul class="fs-12 text-muted ps-3 mb-0">
                                <li class="mb-1"><strong>Method:</strong> {{ $capa->rca_analysis_json['fishbone']['method'] ?? '—' }}</li>
                                <li class="mb-1"><strong>Machine:</strong> {{ $capa->rca_analysis_json['fishbone']['machine'] ?? '—' }}</li>
                                <li><strong>Man:</strong> {{ $capa->rca_analysis_json['fishbone']['man'] ?? '—' }}</li>
                            </ul>
                        </div>
                    </div>
                @endif

                {{-- Verification & Closure --}}
                <div>
                    @if($capa->status === 'active')
                        <div class="border p-3 rounded border-success bg-light">
                            <h6 class="fw-bold text-success mb-3"><i class="feather-check-circle me-1"></i>Verify Effectiveness & Close CAPA</h6>
                            <form method="POST" action="{{ route('production.quality.capas.close', $capa->id) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted fs-12 fw-semibold">Effectiveness Review Comments</label>
                                    <textarea name="effectiveness_review" class="form-control form-control-sm" rows="2" placeholder="Record verification and audit review notes..." required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted fs-12 fw-semibold">E-Signature Token PIN</label>
                                    <input type="text" name="esignature" class="form-control form-control-sm" placeholder="Enter Sign-off Authentication Signature" required>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm w-100">Approve, Close and Archive CAPA</button>
                            </form>
                        </div>
                    @elseif($capa->status === 'closed')
                        <div class="alert alert-success d-flex align-items-center mb-0 py-3">
                            <i class="feather-check-circle fs-20 me-3"></i>
                            <div>
                                <strong class="d-block mb-1">Closed and Verified</strong>
                                <span class="fs-12 text-success-800 d-block mb-1">Review Comments: <em>{{ $capa->effectiveness_review }}</em></span>
                                <span class="font-monospace text-muted fs-10">Signature Pin: {{ $capa->esignature_closed }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
