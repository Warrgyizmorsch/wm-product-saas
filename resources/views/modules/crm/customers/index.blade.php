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

    <div class="row g-4">
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Total Customers</span>
                    <h3 class="mb-0 mt-2">{{ $summary['total'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <span class="text-muted fs-12 text-uppercase">Active Customers</span>
                    <h3 class="mb-0 mt-2">{{ $summary['active'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Customer Workspace</h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                <div>
                    <h6 class="mb-1">CRM module starter</h6>
                    <p class="fs-12 text-muted mb-0">This page is tenant-scoped through BaseModel and ready for the CRM team to extend.</p>
                </div>
                <span class="badge bg-soft-success text-success">Tenant scoped</span>
            </div>
        </div>
    </div>
@endsection
