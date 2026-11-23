@extends('layouts.duralux')

@section('title', 'Vendors pending verification')
@section('page-title', 'Vendor verification')
@section('breadcrumb', 'Pending verification')

@section('page-actions')
    <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border">Back to vendors</a>
@endsection

@section('content')
    @include('partials.party-verification-queue', ['parties' => $vendors, 'type' => 'vendor'])
    @include('partials.party-branch-verification-queue', ['branches' => $pendingBranches ?? collect(), 'type' => 'vendor', 'verification' => $verification])
@endsection
