@extends('layouts.duralux')

@section('title', 'Edit Plan | SaaS ERP')
@section('page-title', 'Edit Plan')
@section('breadcrumb', 'Platform / Plans / Edit')

@section('page-actions')
    <x-ui.button href="{{ route('platform.plans.index') }}" variant="light" icon="feather-arrow-left">
        Back to Plans
    </x-ui.button>
@endsection

@section('content')
    @include('modules.platform.plans.form', [
        'plan' => $plan,
        'action' => route('platform.plans.update', $plan),
        'method' => 'PUT',
        'submitLabel' => 'Update Plan',
    ])
@endsection
