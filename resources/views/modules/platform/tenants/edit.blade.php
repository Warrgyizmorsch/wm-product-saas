@extends('layouts.duralux')

@section('title', 'Edit Tenant | SaaS ERP')
@section('page-title', 'Edit Tenant')
@section('breadcrumb', 'Platform / Tenants / Edit')

@section('page-actions')
    <a href="{{ route('platform.tenants.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Tenants
    </a>
@endsection

@section('content')
    @include('modules.platform.tenants.form', [
        'tenant' => $tenant,
        'action' => route('platform.tenants.update', $tenant),
        'method' => 'PUT',
        'submitLabel' => 'Update Tenant',
    ])
@endsection
