@extends('layouts.admin')

@section('title', 'Edit Tenant | Platform Console')
@section('page-title', 'Edit Tenant')
@section('breadcrumb', 'Tenants / Edit')

@section('page-actions')
    <x-ui.button href="{{ route('platform.tenants.index') }}" variant="light" icon="feather-arrow-left">
        Back to Tenants
    </x-ui.button>
@endsection

@section('content')
    @include('modules.platform.tenants.form', [
        'tenant' => $tenant,
        'action' => route('platform.tenants.update', $tenant),
        'method' => 'PUT',
        'submitLabel' => 'Update Tenant',
        'formContext' => 'edit',
        'tenantId' => $tenant->id,
        'planOptions' => $planOptions,
    ])
@endsection
