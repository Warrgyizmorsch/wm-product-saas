@extends('layouts.duralux')

@section('title', 'Customers | SaaS ERP')
@section('page-title', 'Customers')
@section('breadcrumb', 'CRM / Customers')

@section('page-actions')
    <a href="{{ route('crm.customers.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>New Customer
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
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Total Customers</span>
                    <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full border-0 shadow-sm">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Active Customers</span>
                    <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['active'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-users me-2 text-primary"></i>Customer Directory
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Created Date</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-text avatar-md bg-soft-primary text-primary fs-12 fw-bold me-3">
                                            {{ strtoupper(substr($customer->name, 0, 1)) }}
                                        </div>
                                        <span class="fw-bold">{{ $customer->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $customer->email ?: '—' }}</td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>
                                    @if ($customer->status === 'active')
                                        <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-semibold">Active</span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger px-2 py-0.5 fs-11 fw-semibold">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4 text-muted">{{ $customer->created_at ? $customer->created_at->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="feather-users fs-1 mb-2 d-block"></i>
                                    No customers found in this tenant workspace.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
