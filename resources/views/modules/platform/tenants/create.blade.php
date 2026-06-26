@extends('layouts.duralux')

@section('title', 'Add Tenant | SaaS ERP')
@section('page-title', 'Add Tenant')
@section('breadcrumb', 'Platform / Tenants / Add')

@section('page-actions')
    <a href="{{ route('platform.tenants.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Tenants
    </a>
@endsection

@section('content')
    @include('modules.platform.tenants.form', [
        'tenant' => $tenant,
        'action' => route('platform.tenants.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Tenant',
    ])
@endsection
