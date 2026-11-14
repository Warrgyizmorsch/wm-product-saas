@extends('layouts.admin')

@section('title', 'Edit Plan | Platform Console')
@section('page-title', 'Edit Plan')
@section('breadcrumb', 'Plans / Edit')

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
