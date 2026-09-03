@extends('layouts.duralux')

@section('title', 'Offboarding Policies | SaaS ERP')
@section('page-title', 'Offboarding Policies')
@section('breadcrumb', 'HRMS / HRMS Masters / Offboarding Policies')

@push('styles')
<style>
    .policy-cat-card {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid #e2e8f0 !important;
        background: #ffffff;
    }
    .policy-cat-card:hover {
        border-color: #cbd5e1 !important;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.03) !important;
    }
    .policy-item-box {
        transition: all 0.15s ease-in-out;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
        background-color: #ffffff;
        padding: 12px 14px;
    }
    .policy-item-box:hover {
        background-color: #f8fafc;
        border-color: #cbd5e1;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    }

    /* Modal Form Alignment & Spacing */
    .modal .odoo-form-group {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        margin-bottom: 0 !important;
        width: 100% !important;
    }
    .modal .odoo-form-label {
        width: 100% !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        color: #475569 !important;
        margin-bottom: 6px !important;
    }
    .modal .odoo-form-group .flex-grow-1 {
        width: 100% !important;
        min-width: 100% !important;
    }
    .modal .odoo-form-control {
        width: 100% !important;
        padding: 6px 0 !important;
        font-size: 13.5px !important;
    }
    .modal textarea.odoo-form-control {
        padding: 8px 10px !important;
        border-radius: 6px !important;
    }
    .modal .form-check {
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-rotate-ccw" data-bs-toggle="modal" data-bs-target="#resetTemplatesModal" class="border fw-semibold">
            Reset to Defaults
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold text-uppercase">
            Add Checklist Point
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid p-0">

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

    <!-- Main ERP Single Panel -->
    <div class="erp-single-panel bg-white p-4">
        
        <!-- Header & Filter Toolbar using Standard x-ui Components -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 pb-3 border-bottom">
            <!-- Left Info Banner -->
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-15">Clearance Sign-off Policies & Checklist Master</h5>
                <span class="text-muted fs-12">Configure departmental verification points and clearance obligations for offboarding workflows.</span>
            </div>

            <!-- Right Toolbar: Standard x-ui.filter Dropdown -->
            <div class="d-flex align-items-center gap-2 ms-auto">
                <form method="GET" action="{{ route('hrms.offboarding-policies.index') }}" class="d-inline m-0">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Policies</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Company Scope</label>
                            <x-ui.odoo-form-ui type="select" name="company_id">
                                <option value="">All Companies (Global Policy)</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" @selected($selectedCompanyId == $comp->id)>{{ $comp->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Clearance Authority</label>
                            <x-ui.odoo-form-ui type="select" name="category">
                                <option value="">All Authorities / Categories</option>
                                @foreach($availableCategories as $key => $name)
                                    <option value="{{ $key }}" @selected($selectedCategory == $key)>{{ $name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Sign-off Requirement</label>
                            <x-ui.odoo-form-ui type="select" name="is_mandatory">
                                <option value="">All Requirements</option>
                                <option value="1" @selected($selectedMandatory === '1')>Mandatory Sign-off</option>
                                <option value="0" @selected($selectedMandatory === '0')>Optional Sign-off</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="1" @selected($selectedStatus === '1')>Active Only</option>
                                <option value="0" @selected($selectedStatus === '0')>Inactive Only</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.offboarding-policies.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <x-ui.button type="submit" variant="primary" size="sm">Apply Filters</x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="row g-4">
            @forelse($clearanceCategories as $category)
                <div class="col-xl-6">
                    <div class="card border rounded-4 shadow-none h-100 mb-0 policy-cat-card">
                        <div class="card-header bg-light border-bottom p-3.5 d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-14">{{ $category['category_name'] }}</h6>
                                <span class="text-muted fs-11">Authority Key: <code class="text-dark bg-white px-1.5 py-0.5 rounded border border-light-subtle">{{ $category['category_key'] }}</code></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.badge soft variant="primary" class="px-2.5 py-1 fw-bold fs-11">
                                    <i class="feather-check-circle me-1 text-primary"></i>{{ $category['active_items'] }}/{{ $category['total_items'] }} Active
                                </x-ui.badge>
                                <x-ui.button variant="primary" size="sm" icon="feather-plus" class="fw-semibold" onclick="openCreateWithCategory('{{ $category['category_key'] }}', '{{ addslashes($category['category_name']) }}')">
                                    Add Point
                                </x-ui.button>
                                <x-ui.icon-btn variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Category" onclick="openDeleteCategoryModal('{{ $category['category_key'] }}', '{{ addslashes($category['category_name']) }}', {{ $category['total_items'] }})" />
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex flex-column gap-2.5">
                                @foreach($category['items'] as $item)
                                    @php
                                        $isObj = is_object($item);
                                        $itemId = $isObj ? $item->id : ($item['id'] ?? 'item_' . $loop->index);
                                        $itemName = $isObj ? $item->item_name : $item['item_name'];
                                        $itemDesc = $isObj ? ($item->description ?? '') : ($item['description'] ?? '');
                                        $isMandatory = (bool)($isObj ? $item->is_mandatory : ($item['is_mandatory'] ?? true));
                                        $isActive = (bool)($isObj ? $item->status : ($item['status'] ?? true));
                                        $itemComp = $isObj && $item->company ? $item->company->company_name : null;
                                    @endphp
                                    <div class="policy-item-box d-flex align-items-start justify-content-between gap-3 {{ !$isActive ? 'opacity-75 bg-light' : '' }}">
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap mb-1.5">
                                                <span class="fw-bold text-dark fs-13">{{ $itemName }}</span>
                                                @if($isMandatory)
                                                    <x-ui.badge soft variant="danger" class="fs-10 px-1.5 py-0.5">Required</x-ui.badge>
                                                @else
                                                    <x-ui.badge soft variant="secondary" class="fs-10 px-1.5 py-0.5">Optional</x-ui.badge>
                                                @endif
                                                @if($itemComp)
                                                    <x-ui.badge soft variant="info" class="fs-10 px-1.5 py-0.5"><i class="feather-briefcase me-0.5"></i>{{ $itemComp }}</x-ui.badge>
                                                @else
                                                    <x-ui.badge soft variant="light" class="border fs-10 px-1.5 py-0.5 text-muted">All Companies</x-ui.badge>
                                                @endif
                                                @if(!$isActive)
                                                    <x-ui.badge soft variant="dark" class="fs-10 px-1.5 py-0.5 text-muted">Inactive</x-ui.badge>
                                                @endif
                                            </div>
                                            @if($itemDesc)
                                                <div class="text-muted fs-12 lh-base text-break">
                                                    {{ $itemDesc }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                            @if(is_numeric($itemId))
                                                <x-ui.icon-btn variant="light-brand" size="sm" icon="feather-edit-2" title="Edit Point" data-bs-toggle="modal" data-bs-target="#editTemplateModal_{{ $itemId }}" />
                                                <x-ui.icon-btn variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Point" onclick="openDeleteModal('{{ route('hrms.offboarding-policies.destroy', $itemId) }}', '{{ addslashes($itemName) }}')" />
                                            @else
                                                <x-ui.badge soft variant="secondary" class="fs-10 px-2 py-1">System Default</x-ui.badge>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5 text-muted">
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                        <i class="feather-sliders fs-24"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark">No clearance policy points found</h6>
                    <p class="fs-13 mb-3 text-muted">No items matched your current filter criteria. You can reset filters or add a new checklist point.</p>
                    <div class="d-flex justify-content-center gap-2">
                        @if($hasActiveFilters)
                            <x-ui.button variant="light" icon="feather-rotate-ccw" href="{{ route('hrms.offboarding-policies.index') }}" class="border fw-semibold px-3 py-2">
                                Reset Filters
                            </x-ui.button>
                        @endif
                        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold px-3 py-2">
                            Add Checklist Point
                        </x-ui.button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- ── MODALS SECTION ── -->

<!-- 1. Create Template Item Modal (With Multi-Row Cloning Support) -->
<div class="modal fade text-start" id="createTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-plus-circle text-primary me-2"></i>Add Clearance Policy Checklist Points</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hrms.offboarding-policies.store') }}" id="createTemplateForm">
                @csrf
                <div class="modal-body p-4">
                    <!-- Scope & Authority Selection -->
                    <div class="row g-3 mb-4 pb-3 border-bottom">
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id">
                                <option value="">All Companies (Tenant Global Policy)</option>
                                @foreach($companies as $comp)
                                    <option value="{{ $comp->id }}" @selected($selectedCompanyId == $comp->id)>{{ $comp->company_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" label="Clearance Authority" id="create_category_select" name="clearance_category_select" onchange="onCategorySelectChange(this)" :required="true">
                                <option value="it_assets" data-name="IT & Systems">IT & Systems (it_assets)</option>
                                <option value="facilities_admin" data-name="Facilities & Admin">Facilities & Admin (facilities_admin)</option>
                                <option value="finance_payroll" data-name="Finance & Payroll">Finance & Payroll (finance_payroll)</option>
                                <option value="hr_operations" data-name="HR & Operations">HR & Operations (hr_operations)</option>
                                <option value="reporting_manager" data-name="Reporting Manager">Reporting Manager (reporting_manager)</option>
                                <option value="legal_compliance" data-name="Legal & Compliance">Legal & Compliance (legal_compliance)</option>
                                <option value="__custom__">+ Add New Custom Category...</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-4" id="category_name_wrap">
                            <x-ui.odoo-form-ui type="input" label="Category Display Name" name="category_name" id="create_category_name" value="IT & Systems" placeholder="e.g. Security & Surveillance" :required="true" />
                        </div>

                        <div class="col-md-4" id="custom_cat_key_wrap" style="display: none;">
                            <x-ui.odoo-form-ui type="input" label="Category Slug / Key" name="clearance_category" id="create_clearance_category" value="it_assets" placeholder="e.g. security_operations" :required="true" />
                        </div>
                    </div>

                    <!-- Dynamic Checklist Line Items Grid -->
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-13">Checklist Line Items</h6>
                            <span class="text-muted fs-11">Add one or multiple verification tasks for this clearance category.</span>
                        </div>
                        <x-ui.button type="button" variant="light-brand" size="sm" icon="feather-plus" onclick="addChecklistRow()">
                            Add Another Line Item
                        </x-ui.button>
                    </div>

                    <div class="table-responsive border rounded-3 bg-white mb-2">
                        <x-ui.odoo-form-ui type="table" id="checklistPointsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 38%;" class="ps-3 py-2 fs-11 text-uppercase text-muted fw-bold">Checklist Item / Action <span class="text-danger">*</span></th>
                                    <th style="width: 40%;" class="py-2 fs-11 text-uppercase text-muted fw-bold">Verification Guidelines / Description</th>
                                    <th style="width: 10%;" class="py-2 fs-11 text-uppercase text-muted fw-bold text-center">Sort Order</th>
                                    <th style="width: 7%;" class="py-2 fs-11 text-uppercase text-muted fw-bold text-center">Mandatory</th>
                                    <th style="width: 5%;" class="pe-3 py-2 fs-11 text-uppercase text-muted fw-bold text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="checklistRowsContainer">
                                <tr class="checklist-row" data-index="0">
                                    <td class="ps-3 py-2">
                                        <input type="text" name="items[0][item_name]" class="odoo-table-input item-name-input" placeholder="e.g. Return Laptop & Power Adapter" required>
                                    </td>
                                    <td class="py-2">
                                        <input type="text" name="items[0][description]" class="odoo-table-input" placeholder="e.g. Check hardware serial number and physical condition...">
                                    </td>
                                    <td class="py-2 text-center">
                                        <input type="number" name="items[0][sort_order]" class="odoo-table-input text-center sort-order-input" value="1" min="0">
                                    </td>
                                    <td class="py-2 text-center">
                                        <div class="form-check d-flex justify-content-center m-0">
                                            <input type="checkbox" name="items[0][is_mandatory]" value="1" class="form-check-input" checked title="Mandatory Sign-off">
                                        </div>
                                    </td>
                                    <td class="pe-3 py-2 text-center">
                                        <button type="button" class="btn btn-sm btn-light text-danger border-0 rounded-circle remove-row-btn p-1" title="Remove Row" onclick="removeChecklistRow(this)">
                                            <i class="feather-trash-2 fs-13"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check me-1"></i> Save All Checklist Points
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Template Items Modals -->
@foreach($clearanceTemplates as $tpl)
    @if(is_object($tpl) && is_numeric($tpl->id))
        <div class="modal fade text-start" id="editTemplateModal_{{ $tpl->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-light border-bottom p-3">
                        <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-edit-2 text-primary me-2"></i>Edit Checklist Point - {{ $tpl->item_name }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('hrms.offboarding-policies.update', $tpl->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-body p-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id">
                                        <option value="">All Companies (Tenant Global Policy)</option>
                                        @foreach($companies as $comp)
                                            <option value="{{ $comp->id }}" @selected($tpl->company_id == $comp->id)>{{ $comp->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="Category Slug / Key" name="clearance_category" value="{{ $tpl->clearance_category }}" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" label="Category Display Name" name="category_name" value="{{ $tpl->category_name }}" :required="true" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Sort Order" name="sort_order" value="{{ $tpl->sort_order }}" min="0" helperText="Controls sequential checklist display order (e.g. 1, 2, 3)" />
                                </div>

                                <div class="col-md-12">
                                    <x-ui.odoo-form-ui type="input" label="Checklist Item Name / Action" name="item_name" value="{{ $tpl->item_name }}" :required="true" />
                                </div>

                                <div class="col-md-12">
                                    <x-ui.odoo-form-ui type="textarea" label="Verification Guidelines / Description" name="description" value="{{ $tpl->description }}" :rows="3" />
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="checkbox" label="Sign-off Requirement" name="is_mandatory" value="1" id="edit_mand_{{ $tpl->id }}" :checked="$tpl->is_mandatory">
                                        Mandatory Sign-off
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-md-6">
                                    <x-ui.odoo-form-ui type="checkbox" label="Item Status" name="status" value="1" id="edit_stat_{{ $tpl->id }}" :checked="$tpl->status">
                                        Active Point
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                            <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                            <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                <i class="feather-check-circle me-1"></i> Update Point
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach

<!-- 3. Reset to Defaults Modal -->
<div class="modal fade text-start" id="resetTemplatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-rotate-ccw text-warning me-2"></i>Reset Clearance Policies to Defaults</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hrms.offboarding-policies.reset') }}">
                @csrf
                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                <div class="modal-body p-4">
                    <div class="p-3 bg-soft-warning rounded-3 border border-warning border-opacity-25 mb-3">
                        <div class="fw-bold text-dark mb-1"><i class="feather-alert-triangle text-warning me-1"></i> Are you sure?</div>
                        <div class="fs-12 text-muted">
                            This will replace current {{ $selectedCompanyId ? 'company-specific' : 'tenant global' }} clearance checklist points with the <strong>12 standard enterprise default items</strong> across IT, Facilities, Finance, HR, and Reporting Manager.
                        </div>
                    </div>
                    <p class="fs-12 text-muted mb-0">Active exit cases currently in progress will remain completely unaffected.</p>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="danger" type="submit" class="px-4 fw-bold">
                        <i class="feather-rotate-ccw me-1"></i> Confirm & Reset
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Delete Checklist Point Confirmation Modal -->
<div class="modal fade text-start" id="deletePointModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-alert-triangle text-danger me-2"></i>Delete Checklist Point</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deletePointForm" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body p-4">
                    <div class="p-3 bg-soft-danger rounded-3 border border-danger border-opacity-25 mb-3">
                        <div class="fw-bold text-dark mb-1">Confirm Permanent Deletion</div>
                        <div class="fs-12 text-muted">
                            Are you sure you want to remove <strong id="deletePointName" class="text-dark"></strong> from this clearance policy?
                        </div>
                    </div>
                    <p class="fs-12 text-muted mb-0">Active employee exit cases already in progress with this clearance task will not be affected.</p>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="danger" type="submit" class="px-4 fw-bold">
                        <i class="feather-trash-2 me-1"></i> Delete Point
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. Delete Entire Category Confirmation Modal -->
<div class="modal fade text-start" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-alert-triangle text-danger me-2"></i>Delete Policy Category</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deleteCategoryForm" action="{{ route('hrms.offboarding-policies.destroy-category') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="clearance_category" id="deleteCategoryKey" value="">
                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                <div class="modal-body p-4">
                    <div class="p-3 bg-soft-danger rounded-3 border border-danger border-opacity-25 mb-3">
                        <div class="fw-bold text-dark mb-1">Confirm Category Deletion</div>
                        <div class="fs-12 text-muted">
                            Are you sure you want to completely delete the <strong id="deleteCategoryName" class="text-dark"></strong> category and all its <strong id="deleteCategoryCount"></strong>?
                        </div>
                    </div>
                    <p class="fs-12 text-muted mb-0">Active employee exit cases already in progress will not be affected.</p>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="danger" type="submit" class="px-4 fw-bold">
                        <i class="feather-trash-2 me-1"></i> Delete Category
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function onCategorySelectChange(selectElem) {
        var val = $(selectElem).val();
        var opt = selectElem.options ? selectElem.options[selectElem.selectedIndex] : null;
        var name = opt ? opt.getAttribute('data-name') : null;

        if (val === '__custom__') {
            $('#custom_cat_key_wrap').show();
            $('#create_clearance_category').val('').focus();
            $('#create_category_name').val('');
        } else {
            $('#custom_cat_key_wrap').hide();
            $('#create_clearance_category').val(val);
            $('#create_category_name').val(name || val);
        }
    }

    function openCreateWithCategory(categoryKey, categoryName) {
        var $select = $('#create_category_select');
        var found = false;
        $select.find('option').each(function() {
            if ($(this).val() === categoryKey) {
                $select.val(categoryKey).trigger('change.select2');
                found = true;
                return false;
            }
        });

        if (!found) {
            $select.val('__custom__').trigger('change.select2');
            $('#custom_cat_key_wrap').show();
            $('#create_clearance_category').val(categoryKey);
            $('#create_category_name').val(categoryName);
        } else {
            $('#custom_cat_key_wrap').hide();
            $('#create_clearance_category').val(categoryKey);
            $('#create_category_name').val(categoryName);
        }

        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createTemplateModal'));
        modal.show();
    }

    function openDeleteModal(deleteUrl, itemName) {
        $('#deletePointForm').attr('action', deleteUrl);
        $('#deletePointName').text('"' + itemName + '"');
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePointModal'));
        modal.show();
    }

    function openDeleteCategoryModal(categoryKey, categoryName, itemCount) {
        $('#deleteCategoryKey').val(categoryKey);
        $('#deleteCategoryName').text('"' + categoryName + '"');
        $('#deleteCategoryCount').text(itemCount + ' ' + (itemCount === 1 ? 'checklist point' : 'checklist points'));
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteCategoryModal'));
        modal.show();
    }

    var checklistRowIndex = 0;

    function addChecklistRow(data) {
        checklistRowIndex++;
        data = data || {};
        
        // Find highest current sort order
        var maxSort = 0;
        $('#checklistRowsContainer .sort-order-input').each(function() {
            var v = parseInt($(this).val()) || 0;
            if (v > maxSort) maxSort = v;
        });
        var nextSort = (typeof data.sort_order !== 'undefined') ? data.sort_order : (maxSort + 1);

        var isMandatory = (typeof data.is_mandatory !== 'undefined') ? data.is_mandatory : true;
        var itemName = data.item_name || '';
        var description = data.description || '';

        var html = '<tr class="checklist-row" data-index="' + checklistRowIndex + '">' +
            '<td class="ps-3 py-2">' +
                '<input type="text" name="items[' + checklistRowIndex + '][item_name]" class="odoo-table-input item-name-input" value="' + itemName + '" placeholder="e.g. Return Company Laptop / Access Badge" required>' +
            '</td>' +
            '<td class="py-2">' +
                '<input type="text" name="items[' + checklistRowIndex + '][description]" class="odoo-table-input" value="' + description + '" placeholder="Explain verification guidelines...">' +
            '</td>' +
            '<td class="py-2 text-center">' +
                '<input type="number" name="items[' + checklistRowIndex + '][sort_order]" class="odoo-table-input text-center sort-order-input" value="' + nextSort + '" min="0">' +
            '</td>' +
            '<td class="py-2 text-center">' +
                '<div class="form-check d-flex justify-content-center m-0">' +
                    '<input type="checkbox" name="items[' + checklistRowIndex + '][is_mandatory]" value="1" class="form-check-input" ' + (isMandatory ? 'checked' : '') + ' title="Mandatory Sign-off">' +
                '</div>' +
            '</td>' +
            '<td class="pe-3 py-2 text-center">' +
                '<button type="button" class="btn btn-sm btn-light text-danger border-0 rounded-circle remove-row-btn p-1" title="Remove Row" onclick="removeChecklistRow(this)">' +
                    '<i class="feather-trash-2 fs-13"></i>' +
                '</button>' +
            '</td>' +
        '</tr>';

        $('#checklistRowsContainer').append(html);
        $('#checklistRowsContainer tr:last-child .item-name-input').focus();
    }

    function removeChecklistRow(btn) {
        var $rows = $('#checklistRowsContainer tr.checklist-row');
        if ($rows.length > 1) {
            $(btn).closest('tr').remove();
        } else {
            // Reset fields on single row instead of removing
            var $row = $(btn).closest('tr');
            $row.find('.item-name-input').val('');
            $row.find('input[name*="[description]"]').val('');
            $row.find('.sort-order-input').val(1);
            $row.find('input[type="checkbox"]').prop('checked', true);
        }
    }

    $(document).ready(function() {
        $('[id^="createTemplateModal"], [id^="editTemplateModal_"], [id^="resetTemplatesModal"], #deletePointModal, #deleteCategoryModal').appendTo('body');
    });
</script>
@endpush
