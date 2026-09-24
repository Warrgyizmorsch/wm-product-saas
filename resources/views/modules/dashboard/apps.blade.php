@extends('layouts.duralux')

@section('title', __('ui.modules') . ' | SaaS ERP')
@section('page-title', __('ui.modules'))
@section('breadcrumb', __('ui.modules'))

@section('content')
    <div class="row g-3 erp-apps-grid">
        @forelse ($apps as $app)
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a href="{{ $app['url'] }}" class="card h-100 text-decoration-none erp-app-tile {{ $app['active'] ? 'active' : '' }}">
                    <div class="card-body d-flex flex-column align-items-center text-center gap-3 py-4">
                        <span class="erp-app-icon erp-app-icon-lg" style="background: {{ $app['color'] ?? '#3B82F6' }}">
                            <i class="{{ $app['icon'] }}"></i>
                        </span>
                        <div>
                            <h6 class="fw-bolder text-dark mb-1">{{ $app['label'] }}</h6>
                            <p class="fs-12 text-muted mb-0">{{ $app['description'] }}</p>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-muted">No modules are available for your role.</div></div>
            </div>
        @endforelse
    </div>
@endsection

<style>
.erp-app-tile { transition: all .2s ease; border: 1px solid rgba(0,0,0,.08); }
.erp-app-tile:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.1); border-color: rgba(var(--bs-primary-rgb, 59,130,246), .3); }
.erp-app-tile.active { border-color: var(--bs-primary, #3B82F6); box-shadow: 0 0 0 1px var(--bs-primary, #3B82F6) inset; }
.erp-app-icon-lg { border-radius: 16px; height: 56px; width: 56px; box-shadow: 0 6px 14px -6px rgba(0,0,0,.35); }
.erp-app-icon-lg i { font-size: 24px; }
</style>
