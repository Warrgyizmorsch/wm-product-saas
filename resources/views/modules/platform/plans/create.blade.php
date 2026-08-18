@extends('layouts.duralux')

@section('title', 'Add Plan | SaaS ERP')
@section('page-title', 'Add Plan')
@section('breadcrumb', 'Platform / Plans / Add')

@section('page-actions')
    <a href="{{ route('platform.plans.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Plans
    </a>
@endsection

@section('content')
    @include('modules.platform.plans.form', [
        'plan' => $plan,
        'action' => route('platform.plans.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Plan',
    ])
@endsection
