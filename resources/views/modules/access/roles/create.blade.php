@extends('layouts.duralux')

@section('title', 'Add Role | SaaS ERP')
@section('page-title', 'Add Role')
@section('breadcrumb', 'Access / Roles / Add')

@section('page-actions')
    <a href="{{ route('access.roles.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Roles
    </a>
@endsection

@section('content')
    @include('modules.access.roles.form')
@endsection
