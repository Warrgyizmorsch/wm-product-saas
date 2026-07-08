@extends('layouts.duralux')

@section('title', 'Add User | SaaS ERP')
@section('page-title', 'Add User')
@section('breadcrumb', 'Access / Users / Add')

@section('page-actions')
    <a href="{{ route('access.users.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Users
    </a>
@endsection

@section('content')
    @include('modules.access.users.form', [
        'user' => $user,
        'roles' => $roles,
        'action' => route('access.users.store'),
        'method' => 'POST',
        'submitLabel' => 'Create User',
    ])
@endsection
