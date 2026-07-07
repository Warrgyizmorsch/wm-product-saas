@extends('layouts.duralux')

@section('title', 'Roles &amp; Permissions | SaaS ERP')
@section('page-title', 'Roles &amp; Permissions')
@section('breadcrumb', 'Access / Roles')

@section('page-actions')
    <x-ui.button href="{{ route('access.roles.create') }}" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createRoleModal">
        Add Role
    </x-ui.button>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="border-0 shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-1">Success</h6>
            <p class="fs-12 mb-0">{{ session('success') }}</p>
        </x-ui.alert>
    @endif

    <x-ui.card title="Roles" bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">Role</th>
                    <th>Slug</th>
                    <th>Level</th>
                    <th>Visibility</th>
                    <th>Permissions</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td class="ps-4">
                            <span class="d-block fw-semibold text-dark">{{ $role->name }}</span>
                            @if ($role->description)
                                <span class="fs-11 text-muted">{{ $role->description }}</span>
                            @endif
                        </td>
                        <td>{{ $role->slug }}</td>
                        <td>{{ $role->level }}</td>
                        <td>
                            @if ($role->tenant_id === null)
                                <x-ui.badge variant="secondary" soft>Global</x-ui.badge>
                            @else
                                <x-ui.badge variant="info" soft>Custom</x-ui.badge>
                            @endif
                        </td>
                        <td>{{ $role->role_permissions_count }} grant{{ $role->role_permissions_count === 1 ? '' : 's' }}</td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn href="{{ route('access.roles.show', $role) }}" variant="soft-info" size="md" icon="feather-eye" aria-label="View {{ $role->name }}" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">No roles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal id="createRoleModal" title="Add Role" size="lg" :scrollable="true" :static="true" :showFooter="false">
        @include('modules.access.roles.form', [
            'modal' => true,
            'formId' => 'createRoleForm',
        ])
    </x-ui.modal>
@endsection
