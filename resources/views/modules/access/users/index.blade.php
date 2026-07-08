@extends('layouts.duralux')

@section('title', 'Users | SaaS ERP')
@section('page-title', 'User Management')
@section('breadcrumb', 'Access / Users')

@section('page-actions')
    <x-ui.button href="{{ route('access.users.create') }}" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createUserModal">
        Add User
    </x-ui.button>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="border-0 shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-1">Success</h6>
            <p class="fs-12 mb-0">{{ session('success') }}</p>
        </x-ui.alert>
    @endif

    <x-ui.card title="Users" bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar-text avatar-md bg-soft-primary text-primary">{{ substr($user->name, 0, 1) }}</span>
                                <span class="d-block fw-semibold text-dark">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($user->primaryRole)
                                <x-ui.badge variant="info" soft>{{ $user->primaryRole->name }}</x-ui.badge>
                            @else
                                <span class="text-muted fs-12">No role assigned</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn href="{{ route('access.users.edit', $user) }}" variant="soft-info" size="md" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}" aria-label="Edit {{ $user->name }}" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">No users yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal id="createUserModal" title="Add User" size="lg" :scrollable="true" :static="true" :showFooter="false">
        @include('modules.access.users.form', [
            'user' => new \App\Models\User(),
            'roles' => $roles,
            'action' => route('access.users.store'),
            'method' => 'POST',
            'submitLabel' => 'Create User',
            'modal' => true,
            'formId' => 'createUserForm',
        ])
    </x-ui.modal>

    @foreach ($users as $user)
        <x-ui.modal id="editUserModal{{ $user->id }}" title="Edit User - {{ $user->name }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
            @include('modules.access.users.form', [
                'user' => $user,
                'roles' => $roles,
                'action' => route('access.users.update', $user),
                'method' => 'PUT',
                'submitLabel' => 'Update User',
                'modal' => true,
                'formId' => 'editUserForm'.$user->id,
            ])
        </x-ui.modal>
    @endforeach
@endsection
