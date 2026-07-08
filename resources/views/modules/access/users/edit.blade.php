@extends('layouts.duralux')

@section('title', 'Edit User | SaaS ERP')
@section('page-title', 'Edit User')
@section('breadcrumb', 'Access / Users / Edit')

@section('page-actions')
    <a href="{{ route('access.users.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Users
    </a>
@endsection

@section('content')
    @include('modules.access.users.form', [
        'user' => $user,
        'roles' => $roles,
        'action' => route('access.users.update', $user),
        'method' => 'PUT',
        'submitLabel' => 'Update User',
    ])
@endsection
