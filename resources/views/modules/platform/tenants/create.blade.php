@extends('layouts.admin')

@section('title', 'Add Tenant | Platform Console')
@section('page-title', 'Add Tenant')
@section('breadcrumb', 'Tenants / Add')

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
        'planOptions' => $planOptions,
    ])
@endsection
