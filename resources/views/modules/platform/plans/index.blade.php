@extends('layouts.duralux')

@section('title', 'Plan Catalog | SaaS ERP')
@section('page-title', 'Plan Catalog')
@section('breadcrumb', 'Platform / Plans')

@section('page-actions')
    <x-ui.button href="{{ route('platform.plans.create') }}" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createPlanModal">
        Add Plan
    </x-ui.button>
@endsection

@section('content')

    <div class="row g-4">
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Total Plans</span>
                <h3 class="mb-0 mt-2">{{ $summary['total'] }}</h3>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card stretch>
                <span class="text-muted fs-12 text-uppercase">Active Plans</span>
                <h3 class="mb-0 mt-2">{{ $summary['active'] }}</h3>
            </x-ui.card>
        </div>
    </div>

    <x-ui.card title="Plans" bodyClass="p-0">
        <x-ui.table>
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">Plan</th>
                            <th>Price</th>
                            <th>Limits</th>
                            <th>Trial</th>
                            <th>Tenants</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="avatar-text avatar-md bg-soft-primary text-primary">{{ substr($plan->name, 0, 1) }}</span>
                                        <div>
                                            <span class="d-block fw-semibold text-dark">{{ $plan->name }}</span>
                                            <span class="fs-11 text-muted">{{ $plan->slug }}</span>
                                            @if ($plan->is_demo)
                                                <x-ui.badge variant="info" soft>Demo</x-ui.badge>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $plan->price > 0 ? $plan->currency.' '.number_format($plan->price).' / '.$plan->billing_cycle : 'Free' }}</td>
                                <td>
                                    <span class="d-block fs-12">{{ $plan->max_users ? $plan->max_users.' users' : 'Users unlimited' }}</span>
                                    <span class="d-block fs-11 text-muted">{{ $plan->max_storage_mb ? number_format($plan->max_storage_mb).' MB' : 'Storage unlimited' }}</span>
                                </td>
                                <td>{{ $plan->trial_days ? $plan->trial_days.' days' : '—' }}</td>
                                <td>{{ $plan->tenants_count }}</td>
                                <td>
                                    <x-ui.badge variant="{{ $plan->is_active ? 'success' : 'secondary' }}" soft>
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="hstack gap-2 justify-content-end">
                                        <x-ui.icon-btn href="{{ route('platform.plans.edit', $plan) }}" variant="soft-info" size="md" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#editPlanModal{{ $plan->id }}" aria-label="Edit {{ $plan->name }}" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No plans created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal id="createPlanModal" title="Add Plan" size="lg" :scrollable="true" :static="true" :showFooter="false">
        @include('modules.platform.plans.form', [
            'plan' => new \App\Domains\Platform\Models\Plan(),
            'action' => route('platform.plans.store'),
            'method' => 'POST',
            'submitLabel' => 'Create Plan',
            'modal' => true,
            'formId' => 'createPlanForm',
        ])
    </x-ui.modal>

    @foreach ($plans as $plan)
        <x-ui.modal id="editPlanModal{{ $plan->id }}" title="Edit Plan - {{ $plan->name }}" size="lg" :scrollable="true" :static="true" :showFooter="false">
            @include('modules.platform.plans.form', [
                'plan' => $plan,
                'action' => route('platform.plans.update', $plan),
                'method' => 'PUT',
                'submitLabel' => 'Update Plan',
                'modal' => true,
                'formId' => 'editPlanForm'.$plan->id,
            ])
        </x-ui.modal>
    @endforeach
@endsection
