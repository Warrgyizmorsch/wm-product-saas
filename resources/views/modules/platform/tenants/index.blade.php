@extends('layouts.duralux')

@section('title', 'Tenant Console | SaaS ERP')
@section('page-title', 'Tenant Console')
@section('breadcrumb', 'Platform / Tenants')

@section('page-actions')
    <x-ui.button href="{{ route('platform.tenants.create') }}" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTenantModal">
        Add Tenant
    </x-ui.button>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="border-0 shadow-sm mb-4">
            <h6 class="alert-heading fw-bold mb-1">Success</h6>
            <p class="fs-12 mb-0">{{ session('success') }}</p>
        </x-ui.alert>
    @endif

    <div class="row g-4">
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Total Tenants</span>
                <h3 class="mb-0 mt-2">{{ $summary['total'] }}</h3>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Active Tenants</span>
                <h3 class="mb-0 mt-2">{{ $summary['active'] }}</h3>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Trial Tenants</span>
                <h3 class="mb-0 mt-2">{{ $summary['trial'] }}</h3>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Suspended Tenants</span>
                <h3 class="mb-0 mt-2">{{ $summary['suspended'] }}</h3>
            </x-ui.card>
        </div>
    </div>

    <x-ui.card title="Tenant Directory" bodyClass="p-0">
        <x-ui.table>
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">Tenant</th>
                            <th>Slug</th>
                            <th>Domain</th>
                            <th>Owner</th>
                            <th>Plan</th>
                            <th>Subscription</th>
                            <th>Status</th>
                            <th>Limits</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar-text avatar-md bg-soft-primary text-primary">{{ substr($tenant->name, 0, 1) }}</span>
                                        <div>
                                            <span class="d-block fw-semibold text-dark">{{ $tenant->name }}</span>
                                            <span class="fs-11 text-muted">{{ $tenant->settings['branch'] ?? 'Main Office' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $tenant->slug }}</td>
                                <td>{{ $tenant->domain ?: 'Local/session' }}</td>
                                <td>{{ $tenant->owner?->name ?: 'Not assigned' }}</td>
                                <td><x-ui.badge variant="info" soft>{{ \App\Models\Tenant::plans()[$tenant->plan] ?? ucfirst($tenant->plan) }}</x-ui.badge></td>
                                <td><x-ui.badge variant="{{ $tenant->subscription_status === \App\Models\Tenant::SUBSCRIPTION_ACTIVE ? 'success' : ($tenant->subscription_status === \App\Models\Tenant::SUBSCRIPTION_PAST_DUE ? 'warning' : 'secondary') }}" soft>{{ \App\Models\Tenant::subscriptionStatuses()[$tenant->subscription_status] ?? ucfirst((string) $tenant->subscription_status) }}</x-ui.badge></td>
                                <td>
                                    <x-ui.badge variant="{{ $tenant->status === \App\Models\Tenant::STATUS_ACTIVE ? 'success' : ($tenant->status === \App\Models\Tenant::STATUS_TRIAL ? 'info' : ($tenant->status === \App\Models\Tenant::STATUS_SUSPENDED ? 'warning' : 'secondary')) }}" soft>
                                        {{ \App\Models\Tenant::statuses()[$tenant->status] ?? ucfirst($tenant->status) }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                    <span class="d-block fs-12">{{ $tenant->max_users ? $tenant->max_users.' users' : 'Users unlimited' }}</span>
                                    <span class="d-block fs-11 text-muted">{{ $tenant->max_storage_mb ? number_format($tenant->max_storage_mb).' MB' : 'Storage unlimited' }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="hstack gap-2 justify-content-end">
                                        <x-ui.icon-btn href="{{ route('tenant.switch', $tenant->slug) }}" variant="soft-success" size="md" icon="feather-repeat" title="Switch" />
                                        <x-ui.icon-btn href="{{ route('platform.tenants.edit', $tenant) }}" variant="soft-info" size="md" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#editTenantModal{{ $tenant->id }}" aria-label="Edit {{ $tenant->name }}" />
                                        <form action="{{ route('platform.tenants.status', $tenant) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $tenant->status === \App\Models\Tenant::STATUS_ACTIVE ? \App\Models\Tenant::STATUS_SUSPENDED : \App\Models\Tenant::STATUS_ACTIVE }}">
                                            <x-ui.icon-btn type="submit" variant="soft-{{ $tenant->status === \App\Models\Tenant::STATUS_ACTIVE ? 'warning' : 'success' }}" size="md" icon="feather-{{ $tenant->status === \App\Models\Tenant::STATUS_ACTIVE ? 'pause' : 'play' }}" title="{{ $tenant->status === \App\Models\Tenant::STATUS_ACTIVE ? 'Suspend' : 'Activate' }}" />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">No tenants created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal id="createTenantModal" title="Add Tenant" size="xl" :scrollable="true" :static="true" :showFooter="false">
        @include('modules.platform.tenants.form', [
            'tenant' => new \App\Models\Tenant(),
            'action' => route('platform.tenants.store'),
            'method' => 'POST',
            'submitLabel' => 'Create Tenant',
            'modal' => true,
            'formId' => 'createTenantForm',
            'formContext' => 'create',
        ])
    </x-ui.modal>

    @foreach ($tenants as $tenant)
        <x-ui.modal id="editTenantModal{{ $tenant->id }}" title="Edit Tenant - {{ $tenant->name }}" size="xl" :scrollable="true" :static="true" :showFooter="false">
            @include('modules.platform.tenants.form', [
                'tenant' => $tenant,
                'action' => route('platform.tenants.update', $tenant),
                'method' => 'PUT',
                'submitLabel' => 'Update Tenant',
                'modal' => true,
                'formId' => 'editTenantForm'.$tenant->id,
                'formContext' => 'edit',
                'tenantId' => $tenant->id,
            ])
        </x-ui.modal>
    @endforeach
@endsection

@if ($errors->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalId = 'createTenantModal';
                @if (old('_tenant_form') === 'edit' && old('_tenant_id'))
                    modalId = 'editTenantModal{{ old('_tenant_id') }}';
                @endif
                var modalEl = document.getElementById(modalId);
                if (modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        </script>
    @endpush
@endif
