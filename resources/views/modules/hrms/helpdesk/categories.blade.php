@extends('layouts.duralux')

@section('title', __('hrms.helpdesk.title_categories'))
@section('page-title', __('hrms.helpdesk.page_title_categories'))
@section('breadcrumb', __('hrms.helpdesk.breadcrumb_categories'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}">
            {{ __('hrms.helpdesk.btn_back_to_helpdesk') }}
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal" class="fw-bold">
            {{ __('hrms.helpdesk.btn_add_category') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    /* Prevent horizontal scrollbar & wrap long content to next line */
    .helpdesk-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .helpdesk-table th,
    .helpdesk-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="border-bottom pb-3 mb-4">
            <h6 class="fw-bold mb-0 text-dark"><i class="feather-settings me-2 text-primary"></i> {{ __('hrms.helpdesk.categories_heading') }}</h6>
        </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 helpdesk-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 23%;">{{ __('hrms.helpdesk.th_category_name') }}</th>
                            <th style="width: 11%;">{{ __('hrms.helpdesk.th_code') }}</th>
                            <th style="width: 18%;">{{ __('hrms.helpdesk.th_target_sla') }}</th>
                            <th style="width: 19%;">{{ __('hrms.helpdesk.th_default_agent') }}</th>
                            <th class="text-center text-nowrap" style="width: 10%;">{{ __('hrms.helpdesk.th_confidential') }}</th>
                            <th class="text-center text-nowrap" style="width: 9%;">{{ __('hrms.helpdesk.th_total_tickets') }}</th>
                            <th class="text-end pe-3 text-nowrap" style="width: 10%;">{{ __('hrms.helpdesk.th_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td class="fw-bold text-dark">
                                    {{ $cat->name }}
                                    @if($cat->description)
                                        <div class="small text-muted font-normal">{{ $cat->description }}</div>
                                    @endif
                                </td>
                                <td><code>{{ $cat->code }}</code></td>
                                <td>
                                    <x-ui.badge variant="info" soft class="fw-bold">
                                        <i class="feather-clock me-1"></i> {{ __('hrms.helpdesk.hours_suffix', ['hours' => $cat->default_sla_hours]) }}
                                    </x-ui.badge>
                                </td>
                                <td>
                                     @if($cat->defaultAgent)
                                         <x-ui.badge variant="light" class="border text-dark">
                                             <i class="feather-user me-1"></i> {{ $cat->defaultAgent->full_name ?? trim(($cat->defaultAgent->first_name ?? '') . ' ' . ($cat->defaultAgent->last_name ?? '')) }}
                                         </x-ui.badge>
                                     @else
                                        <span class="text-muted small">{{ __('hrms.helpdesk.none_unassigned') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($cat->is_confidential)
                                        <x-ui.badge variant="danger" soft><i class="feather-lock me-1"></i> {{ __('hrms.helpdesk.yes') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft>{{ __('hrms.helpdesk.no') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.badge variant="secondary" soft class="fs-7">{{ $cat->tickets_count }}</x-ui.badge>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 justify-content-end">
                                        <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" :title="__('hrms.helpdesk.btn_edit_category')" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $cat->id }}" />
                                        <form action="{{ route('hrms.helpdesk.categories.destroy', $cat->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm(@json(__('hrms.helpdesk.confirm_delete_category')));">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" :title="__('hrms.helpdesk.btn_delete_category')" />
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <x-ui.modal id="editCategoryModal{{ $cat->id }}" :title="'<i class=\'feather-edit me-2\'></i> ' . __('hrms.helpdesk.modal_edit_category_title', ['name' => $cat->name])" formAction="{{ route('hrms.helpdesk.categories.update', $cat->id) }}" formMethod="PUT" :submitText="__('hrms.helpdesk.btn_save_changes')" centered>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.helpdesk.field_category_name')" name="name" value="{{ $cat->name }}" :required="true" />
                                </div>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.helpdesk.field_default_sla_hours')" name="default_sla_hours" value="{{ $cat->default_sla_hours }}" min="1" :required="true" />
                                </div>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_default_agent_routing')" name="default_agent_id">
                                        <option value="">{{ __('hrms.helpdesk.select_default_agent') }}</option>
                                         @foreach($agents as $agent)
                                             <option value="{{ $agent->id }}" {{ $cat->default_agent_id == $agent->id ? 'selected' : '' }}>
                                                 {{ $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) }} ({{ $agent->employee_id ?? $agent->employee_code }})
                                             </option>
                                         @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.helpdesk.field_description')" name="description" rows="2">{{ $cat->description }}</x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <x-ui.checkbox :label="__('hrms.helpdesk.field_mark_confidential')" name="is_confidential" value="1" :checked="$cat->is_confidential" id="editConfSwitch_{{ $cat->id }}" />
                                </div>
                            </x-ui.modal>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    {{ __('hrms.helpdesk.no_categories_defined') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
    </div>
</div>

<!-- Modal: Create Category -->
<x-ui.modal id="createCategoryModal" :title="'<i class=\'feather-plus-circle me-2\'></i> ' . __('hrms.helpdesk.modal_create_category_title')" formAction="{{ route('hrms.helpdesk.categories.store') }}" :submitText="__('hrms.helpdesk.btn_create_category')" centered>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" :label="__('hrms.helpdesk.field_category_name')" name="name" :placeholder="__('hrms.helpdesk.placeholder_category_name')" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.helpdesk.field_default_sla_hours')" name="default_sla_hours" value="24" min="1" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_default_agent_routing')" name="default_agent_id">
            <option value="">{{ __('hrms.helpdesk.select_default_agent') }}</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) }} ({{ $agent->employee_id ?? $agent->employee_code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="textarea" :label="__('hrms.helpdesk.field_description')" name="description" rows="2" :placeholder="__('hrms.helpdesk.placeholder_category_desc')" />
    </div>
    <div class="mb-3">
        <x-ui.checkbox :label="__('hrms.helpdesk.field_mark_confidential')" name="is_confidential" value="1" id="createConfSwitch" />
    </div>
</x-ui.modal>
@endsection
