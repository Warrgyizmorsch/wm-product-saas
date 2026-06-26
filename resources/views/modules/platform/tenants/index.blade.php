@extends('layouts.duralux')

@section('title', 'Tenant Console | SaaS ERP')
@section('page-title', 'Tenant Console')
@section('breadcrumb', 'Platform / Tenants')

@section('page-actions')
    <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Add Tenant
    </a>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Total Tenants</span>
                    <h3 class="mb-0 mt-2">{{ $summary['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Active Tenants</span>
                    <h3 class="mb-0 mt-2">{{ $summary['active'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Tenant Directory</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">Tenant</th>
                            <th>Slug</th>
                            <th>Domain</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Timezone</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar-text avatar-md bg-soft-primary text-primary">{{ substr($tenant->name, 0, 1) }}</span>
                                        <div>
                                            <span class="d-block fw-semibold text-dark">{{ $tenant->name }}</span>
                                            <span class="fs-11 text-muted">{{ $tenant->settings['branch'] ?? 'Main Office' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $tenant->slug }}</td>
                                <td>{{ $tenant->domain ?: 'Local/session' }}</td>
                                <td><span class="badge bg-soft-info text-info">{{ ucfirst($tenant->plan) }}</span></td>
                                <td>
                                    <span class="badge bg-soft-{{ $tenant->status === 'active' ? 'success' : 'secondary' }} text-{{ $tenant->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($tenant->status) }}
                                    </span>
                                </td>
                                <td>{{ $tenant->timezone }}</td>
                                <td class="text-end pe-4">
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="{{ route('tenant.switch', $tenant->slug) }}" class="avatar-text avatar-md bg-soft-success text-success" data-bs-toggle="tooltip" title="Switch">
                                            <i class="feather-repeat"></i>
                                        </a>
                                        <a href="{{ route('platform.tenants.edit', $tenant) }}" class="avatar-text avatar-md bg-soft-info text-info" data-bs-toggle="tooltip" title="Edit">
                                            <i class="feather-edit-3"></i>
                                        </a>
                                        <form action="{{ route('platform.tenants.status', $tenant) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'inactive' : 'active' }}">
                                            <button type="submit" class="avatar-text avatar-md border-0 bg-soft-{{ $tenant->status === 'active' ? 'warning' : 'success' }} text-{{ $tenant->status === 'active' ? 'warning' : 'success' }}" data-bs-toggle="tooltip" title="{{ $tenant->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                                <i class="feather-{{ $tenant->status === 'active' ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No tenants created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
