@extends('layouts.duralux')

@section('title', ($mode === 'deactivate' ? 'Make inactive' : 'Reactivate') . ' | ' . $customer->name)
@section('page-title', $mode === 'deactivate' ? 'Make customer inactive' : 'Reactivate customer')
@section('breadcrumb', $customer->name)

@section('page-actions')
    <a href="{{ route('crm.customers.show', $customer) }}" class="btn btn-light border">Back to customer</a>
@endsection

@section('content')
    @include('partials.party-status-form', [
        'party' => $customer,
        'type' => 'customer',
        'storeRoute' => $mode === 'deactivate' ? route('crm.customers.deactivate.store', $customer) : route('crm.customers.reactivate.store', $customer),
        'backRoute' => route('crm.customers.show', $customer),
    ])
@endsection
