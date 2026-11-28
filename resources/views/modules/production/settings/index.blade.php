@extends('layouts.duralux')

@section('title', 'Production Settings')

{{-- The subcontract PR / PO modes were removed (no PR / PO in subcontracting, docs/production_ui_rules_review_2026-11-27.md SF-4);
     the page keeps the tenant's remnant release policy, which had no screen before. --}}
@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-settings me-2 text-primary"></i>Production Settings</h4>
            <p class="text-muted fs-12 mb-0">Tenant-wide production policies.</p>
        </div>
        <div>
            <a href="{{ route('production.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="feather-arrow-left me-1"></i> Dashboard</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark"><i class="feather-scissors me-2 text-info"></i>Remnant release policy</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('production.settings.remnant-policy.update') }}" method="POST">
                        @csrf
                        <p class="text-muted fs-12 mb-3">What happens to a usable remnant (off-cut) recorded on the shop floor.</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="remnant_release_policy" id="remnant_immediate" value="immediate" @checked($remnantReleasePolicy === 'immediate')>
                            <label class="form-check-label fs-13" for="remnant_immediate"><strong>Release at once</strong> — the remnant goes back to stock immediately.</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="remnant_release_policy" id="remnant_approval" value="require_approval" @checked($remnantReleasePolicy === 'require_approval')>
                            <label class="form-check-label fs-13" for="remnant_approval"><strong>Needs approval</strong> — the remnant waits until a supervisor approves it.</label>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4"><i class="feather-save me-1"></i> Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
