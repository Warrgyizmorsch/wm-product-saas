@extends('layouts.duralux')

@section('title', ($mode === 'deactivate' ? 'Make inactive' : 'Reactivate') . ' | ' . $vendor->name)
@section('page-title', $mode === 'deactivate' ? 'Make vendor inactive' : 'Reactivate vendor')
@section('breadcrumb', $vendor->name)

@section('page-actions')
    <a href="{{ route('purchase.vendors.show', $vendor->id) }}" class="btn btn-light border">Back to vendor</a>
@endsection

@section('content')
    @include('partials.party-status-form', [
        'party' => $vendor,
        'type' => 'vendor',
        'storeRoute' => $mode === 'deactivate' ? route('purchase.vendors.deactivate.store', $vendor->id) : route('purchase.vendors.reactivate.store', $vendor->id),
        'backRoute' => route('purchase.vendors.show', $vendor->id),
    ])
@endsection
