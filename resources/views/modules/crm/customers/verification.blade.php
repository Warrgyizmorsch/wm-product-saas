@extends('layouts.duralux')

@section('title', 'Customers pending verification')
@section('page-title', 'Customer verification')
@section('breadcrumb', 'Pending verification')

@section('page-actions')
    <a href="{{ route('crm.customers.index') }}" class="btn btn-light border">Back to customers</a>
@endsection

@section('content')
    @include('partials.party-verification-queue', ['parties' => $customers, 'type' => 'customer'])
    @include('partials.party-branch-verification-queue', ['branches' => $pendingBranches ?? collect(), 'type' => 'customer', 'verification' => $verification])
@endsection
