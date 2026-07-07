@extends('layouts.duralux')

@php
    $groups = collect($matrix)->groupBy(fn ($row) => $row['permission']->module);
    $scopeLabels = [
        \App\Models\Access\RolePermission::SCOPE_OWN => 'Own',
        \App\Models\Access\RolePermission::SCOPE_TEAM => 'Team',
        \App\Models\Access\RolePermission::SCOPE_DEPARTMENT => 'Department',
        \App\Models\Access\RolePermission::SCOPE_BRANCH => 'Branch',
        \App\Models\Access\RolePermission::SCOPE_TENANT => 'Tenant',
        \App\Models\Access\RolePermission::SCOPE_PLATFORM => 'Platform',
    ];
    $isGlobalRole = $role->tenant_id === null;
@endphp

@section('title', $role->name.' | SaaS ERP')
@section('page-title', $role->name)
@section('breadcrumb', 'Access / Roles / '.$role->name)

@section('page-actions')
    <a href="{{ route('access.roles.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Roles
    </a>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="border-0 shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-1">Success</h6>
            <p class="fs-12 mb-0">{{ session('success') }}</p>
        </x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div>
                <span class="text-muted fs-11 text-uppercase d-block">Slug</span>
                <span class="fw-semibold">{{ $role->slug }}</span>
            </div>
            <div>
                <span class="text-muted fs-11 text-uppercase d-block">Level</span>
                <span class="fw-semibold">{{ $role->level }}</span>
            </div>
            <div>
                <span class="text-muted fs-11 text-uppercase d-block">Visibility</span>
                @if ($isGlobalRole)
                    <x-ui.badge variant="secondary" soft>Global (all tenants)</x-ui.badge>
                @else
                    <x-ui.badge variant="info" soft>Custom (this tenant only)</x-ui.badge>
                @endif
            </div>
            @if ($role->description)
                <div class="flex-grow-1">
                    <span class="text-muted fs-11 text-uppercase d-block">Description</span>
                    <span>{{ $role->description }}</span>
                </div>
            @endif
        </div>
    </x-ui.card>

    @if (! $canEditPermissions)
        <x-ui.alert variant="warning" icon="feather-lock" class="border-0 shadow-sm mb-4">
            <p class="fs-12 mb-0">
                @if ($isGlobalRole)
                    This is a global system role — its permissions affect every tenant, so only a Super Admin can edit it. You're viewing it read-only.
                @else
                    You don't have permission to edit this role. You're viewing it read-only.
                @endif
            </p>
        </x-ui.alert>
    @endif

    <form action="{{ route('access.roles.permissions.update', $role) }}" method="POST">
        @csrf
        @method('PUT')

        <x-ui.card title="Permissions" bodyClass="p-0">
            <x-ui.table>
                @foreach ($groups as $module => $rows)
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">{{ $module }}</th>
                            @foreach ($scopeLabels as $scope => $label)
                                <th class="text-center">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $permission = $row['permission']; @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="d-block fw-semibold">{{ $permission->entity }}.{{ $permission->action }}</span>
                                    @if ($permission->description)
                                        <span class="fs-11 text-muted">{{ $permission->description }}</span>
                                    @endif
                                </td>
                                @foreach ($scopeLabels as $scope => $label)
                                    @php
                                        $isPlatformOnCustomRole = $scope === \App\Models\Access\RolePermission::SCOPE_PLATFORM && ! $isGlobalRole;
                                        $disabled = ! $canEditPermissions || $isPlatformOnCustomRole;
                                    @endphp
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="grants[{{ $permission->id }}][{{ $scope }}]"
                                            value="1"
                                            @checked($row['grants'][$scope])
                                            @disabled($disabled)
                                            title="{{ $isPlatformOnCustomRole ? 'Custom tenant roles cannot hold platform scope.' : '' }}"
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        @if ($canEditPermissions)
            <div class="d-flex justify-content-end mt-3">
                <x-ui.button type="submit" variant="primary" icon="feather-check-circle">Save Permissions</x-ui.button>
            </div>
        @endif
    </form>
@endsection
