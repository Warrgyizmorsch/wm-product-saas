@extends('layouts.duralux')

@section('title', 'Subscription lapsed | SaaS ERP')
@section('page-title', 'Subscription lapsed')
@section('breadcrumb', 'Tenant Console / Subscription')

@section('content')
    <x-ui.card>
        <div class="text-center py-5">
            <i class="feather-lock fs-1 text-warning d-block mb-3"></i>
            <h4 class="mb-2">{{ $tenant->name }}'s subscription has lapsed</h4>
            <p class="text-muted mb-0">
                Your workspace is paused until an administrator renews the subscription.
                Nothing has been deleted — everything will be exactly as you left it.
            </p>
        </div>
    </x-ui.card>
@endsection
