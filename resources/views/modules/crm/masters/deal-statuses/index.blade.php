@extends('layouts.duralux')

@section('title', 'Deal Stage Master | CRM | SaaS ERP')
@section('page-title', 'Deal Stage Master')
@section('breadcrumb', 'CRM / Masters / Deal Stage Master')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createStatusModal">
        New Stage
    </x-ui.button>
@endsection

@push('styles')
<style>
    .status-row-draggable {
        transition: background-color 0.15s ease, transform 0.15s ease;
        user-select: none;
    }
    .status-row-draggable.dragging {
        opacity: 0.45;
        background-color: #eff6ff !important;
    }
    .status-row-draggable.drag-over-top {
        border-top: 2.5px solid var(--bs-primary) !important;
    }
    .status-row-draggable.drag-over-bottom {
        border-bottom: 2.5px solid var(--bs-primary) !important;
    }
    .drag-handle {
        cursor: grab;
        padding: 4px 8px;
        display: inline-flex;
        align-items: center;
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }
    .drag-handle:hover {
        background-color: #e2e8f0;
        color: var(--bs-primary) !important;
    }
    .drag-handle:active {
        cursor: grabbing;
    }
</style>
@endpush

@section('content')
@php
    $sortBy = request('sort_by', 'sort_order');
    $sortOrder = request('sort_order', 'asc');
@endphp

<div class="erp-single-panel">

    {{-- 1. Header & Actions Toolbar --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h5 class="fw-bold text-dark mb-0">Deal Stage Master List</h5>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <form method="GET" action="{{ route('crm.masters.deal-statuses.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                @foreach(request()->except(['search', 'page']) as $k => $v)
                    @if(is_scalar($v) && $v !== '')
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search stage..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                @if(request('search'))
                    <a href="{{ route('crm.masters.deal-statuses.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                        <i class="feather-x fs-12"></i>
                    </a>
                @endif
            </form>

            <!-- Sort Dropdown -->
            <x-ui.sort-dropdown label="Sort">
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sort_order', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'sort_order' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>Order (Ascending)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sort_order', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'sort_order' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>Order (Descending)</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>Stage Name (A - Z)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>Stage Name (Z - A)</span>
                </a>
            </x-ui.sort-dropdown>

            <!-- Filter Dropdown -->
            <form method="GET" action="{{ route('crm.masters.deal-statuses.index') }}" class="d-inline">
                @foreach(request()->except(['type', 'search', 'page']) as $k => $v)
                    @if(is_scalar($v) && $v !== '')
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <x-ui.filter label="Filter" offset="0, 5">
                    <div class="p-3 style-filter-menu" style="min-width: 240px;">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search stage..." value="{{ request('search') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Stage Type</label>
                            <x-ui.odoo-form-ui type="select" name="type">
                                <option value="all" @selected(request('type', 'all') === 'all')>All Types</option>
                                <option value="protected" @selected(request('type') === 'protected')>System Defaults</option>
                                <option value="custom" @selected(request('type') === 'custom')>Custom Masters</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <a href="{{ route('crm.masters.deal-statuses.index') }}" class="btn btn-xs btn-light">Reset</a>
                            <x-ui.button type="submit" variant="primary" size="xs">Apply Filter</x-ui.button>
                        </div>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    {{-- 2. Common Odoo Table --}}
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" id="dealStatusMasterTable" class="mb-0">
            <thead>
                <tr style="background-color: #e8ecf1 !important;">
                    <th style="width: 50px; background-color: #e8ecf1 !important;" class="text-center">Drag</th>
                    <th style="width: 70px; background-color: #e8ecf1 !important;" class="text-center">Order</th>
                    <th style="background-color: #e8ecf1 !important;">Stage Name</th>
                    <th style="width: 140px; background-color: #e8ecf1 !important;" class="text-center">Win Prob. (%)</th>
                    <th style="background-color: #e8ecf1 !important;">Type / Protection</th>
                    <th style="width: 100px; background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
                </tr>
            </thead>
            <tbody id="sortableStatusesBody" class="fs-13 text-dark">
                @forelse($statuses as $index => $st)
                    @php
                        $isProtected = $st->isProtected();
                        $badgeColor = match(strtolower($st->name)) {
                            'qualification' => 'primary',
                            'needs analysis' => 'info',
                            'proposal' => 'warning',
                            'negotiation' => 'dark',
                            'won', 'closed won' => 'success',
                            'lost', 'closed lost' => 'danger',
                            default => str_replace('bg-', '', $st->color ?: 'primary'),
                        };
                        $rowNum = ($statuses->currentPage() - 1) * $statuses->perPage() + $index + 1;
                    @endphp
                    <tr class="status-row-draggable" draggable="true" data-id="{{ $st->id }}">
                        <td class="text-center py-2.5">
                            <div class="drag-handle text-muted" title="Drag to reorder stage" data-bs-toggle="tooltip">
                                <i class="feather-move fs-14 text-primary"></i>
                            </div>
                        </td>
                        <td class="text-center py-2.5">
                            <x-ui.badge variant="secondary" :soft="true" class="font-monospace fs-12 px-2.5 py-1 rounded-pill">
                                #<span class="order-num">{{ $rowNum }}</span>
                            </x-ui.badge>
                        </td>
                        <td>
                            <x-ui.badge :variant="$badgeColor" :soft="true" class="px-3 py-1.5 fs-12 fw-bold">
                                {{ $st->name }}
                            </x-ui.badge>
                        </td>
                        <td class="text-center py-2.5">
                            <x-ui.badge variant="info" :soft="true" class="font-monospace fs-12 px-2.5 py-1 rounded-pill">
                                {{ $st->probability ?? 0 }}%
                            </x-ui.badge>
                        </td>
                        <td>
                            @if($isProtected)
                                <x-ui.badge variant="warning" :soft="true" class="border border-warning-subtle px-2.5 py-1 fs-11" title="Core CRM stage required for deal pipeline logic">
                                    <i class="feather-lock me-1"></i> System Default (Protected)
                                </x-ui.badge>
                            @else
                                <x-ui.badge variant="success" :soft="true" class="border border-success-subtle px-2.5 py-1 fs-11">
                                    <i class="feather-check me-1"></i> Custom Master
                                </x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if(!$isProtected)
                                <x-ui.action-dropdown>
                                    <x-slot:extraActions>
                                        <button type="button" class="action-dropdown-btn"
                                                title="Edit Stage"
                                                data-bs-toggle="tooltip"
                                                onclick="openEditStatusModal({{ $st->id }}, '{{ addslashes($st->name) }}', '{{ $st->color }}', {{ $st->sort_order }}, {{ $st->probability ?? 50 }})">
                                            <i class="feather feather-edit-2"></i>
                                        </button>
                                        <form action="{{ route('crm.masters.deal-statuses.destroy', $st->id) }}" method="POST" class="d-inline" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete stage \'{{ addslashes($st->name) }}\'?', { title: 'Delete Deal Stage', variant: 'danger', confirmButtonText: 'Delete' });">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-dropdown-btn text-danger" title="Delete Stage" data-bs-toggle="tooltip">
                                                <i class="feather feather-trash-2 text-danger"></i>
                                            </button>
                                        </form>
                                    </x-slot:extraActions>
                                </x-ui.action-dropdown>
                            @else
                                <x-ui.action-dropdown>
                                    <x-slot:extraActions>
                                        <button type="button" class="action-dropdown-btn opacity-50 cursor-not-allowed" disabled title="System default stages cannot be edited or deleted" data-bs-toggle="tooltip">
                                            <i class="feather feather-lock text-muted"></i>
                                        </button>
                                    </x-slot:extraActions>
                                </x-ui.action-dropdown>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="feather-inbox display-6 mb-2 text-muted opacity-50 d-block"></i>
                            No deal stages found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    {{-- 3. Common Pagination Component --}}
    @if($statuses->hasPages())
        <div class="mt-3">
            <x-ui.pagination 
                :currentPage="$statuses->currentPage()" 
                :totalPages="$statuses->lastPage()" 
                :totalResults="$statuses->total()" 
                :perPage="$statuses->perPage()" 
            />
        </div>
    @endif
</div>

<!-- Modal: Create New Deal Stage -->
<x-ui.modal
    id="createStatusModal"
    title='<i class="feather-plus-circle me-1.5 text-primary"></i> Add New Deal Stage Master'
    :centered="true"
    :formAction="route('crm.masters.deal-statuses.store')"
    formMethod="POST"
    submitText="Save Stage Master"
    closeText="Cancel"
>
    <x-ui.odoo-form-ui type="input" label="Stage Name" name="name" placeholder="e.g. Qualification, Needs Analysis, Proposal" required="true" />

    <x-ui.odoo-form-ui type="input" inputType="number" label="Win Probability Percentage (%) *" name="probability" value="50" min="0" max="100" required="true" placeholder="e.g. 10, 50, 80" />

    <div class="mb-3">
        <label class="form-label fw-bold text-dark fs-12">Badge Color / Style</label>
        <x-ui.odoo-form-ui type="select" name="color">
            <option value="bg-primary" selected>Primary (Blue)</option>
            <option value="bg-info">Info (Cyan)</option>
            <option value="bg-warning">Warning (Yellow)</option>
            <option value="bg-danger">Danger (Red)</option>
            <option value="bg-success">Success (Green)</option>
            <option value="bg-teal">Teal (Sea Green)</option>
            <option value="bg-secondary">Secondary (Gray)</option>
            <option value="bg-dark">Dark (Black)</option>
        </x-ui.odoo-form-ui>
    </div>

    <x-ui.odoo-form-ui type="input" inputType="number" label="Sort Order Number" name="sort_order" value="{{ $statuses->max('sort_order') + 1 }}" min="1" />
</x-ui.modal>

<!-- Modal: Edit Custom Deal Stage -->
<x-ui.modal
    id="editStatusModal"
    title='<i class="feather-edit me-1.5 text-primary"></i> Edit Deal Stage Master'
    :centered="true"
    :showFooter="false"
>
    <form id="editStatusForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body-content">
            <x-ui.odoo-form-ui type="input" label="Stage Name" name="name" id="edit_status_name" required="true" />

            <x-ui.odoo-form-ui type="input" inputType="number" label="Win Probability Percentage (%) *" name="probability" id="edit_status_probability" min="0" max="100" required="true" placeholder="e.g. 10, 50, 80" />

            <div class="mb-3">
                <label class="form-label fw-bold text-dark fs-12">Badge Color / Style</label>
                <x-ui.odoo-form-ui type="select" name="color" id="edit_status_color">
                    <option value="bg-primary">Primary (Blue)</option>
                    <option value="bg-info">Info (Cyan)</option>
                    <option value="bg-warning">Warning (Yellow)</option>
                    <option value="bg-danger">Danger (Red)</option>
                    <option value="bg-success">Success (Green)</option>
                    <option value="bg-teal">Teal (Sea Green)</option>
                    <option value="bg-secondary">Secondary (Gray)</option>
                    <option value="bg-dark">Dark (Black)</option>
                </x-ui.odoo-form-ui>
            </div>

            <x-ui.odoo-form-ui type="input" inputType="number" label="Sort Order Number" name="sort_order" id="edit_status_sort_order" min="1" />
        </div>
        <div class="modal-footer pt-3 px-0 pb-0 border-top mt-3">
            <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">Cancel</button>
            <x-ui.button type="submit" variant="primary" icon="feather-check">
                Update Stage
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script>
    function openEditStatusModal(id, name, color, sortOrder, probability) {
        const form = document.getElementById('editStatusForm');
        form.action = "{{ url('crm/masters/deal-statuses') }}/" + id;
        document.getElementById('edit_status_name').value = name;
        document.getElementById('edit_status_color').value = color || 'bg-primary';
        document.getElementById('edit_status_sort_order').value = sortOrder || 1;
        document.getElementById('edit_status_probability').value = (probability !== undefined && probability !== null) ? probability : 50;

        const editModal = new bootstrap.Modal(document.getElementById('editStatusModal'));
        editModal.show();
    }

    // HTML5 Drag and Drop Table Reordering Script
    document.addEventListener('DOMContentLoaded', function () {
        const tbody = document.getElementById('sortableStatusesBody');
        if (!tbody) return;

        let draggedRow = null;

        function getRows() {
            return Array.from(tbody.querySelectorAll('.status-row-draggable'));
        }

        function updateOrderNumbers() {
            getRows().forEach((row, idx) => {
                const badgeNum = row.querySelector('.order-num');
                if (badgeNum) {
                    badgeNum.textContent = idx + 1;
                }
            });
        }

        function saveReorderedMap() {
            const rows = getRows();
            const orderMap = {};
            rows.forEach((row, idx) => {
                const id = row.getAttribute('data-id');
                if (id) {
                    orderMap[id] = idx + 1;
                }
            });

            fetch("{{ route('crm.masters.deal-statuses.reorder') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify({ order: orderMap })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        }).fire({
                            icon: 'success',
                            title: data.message || 'Deal stage order updated!'
                        });
                    }
                }
            })
            .catch(err => console.error('Error reordering deal stages:', err));
        }

        getRows().forEach(row => {
            row.addEventListener('dragstart', function (e) {
                draggedRow = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            });

            row.addEventListener('dragend', function () {
                this.classList.remove('dragging');
                getRows().forEach(r => {
                    r.classList.remove('drag-over-top', 'drag-over-bottom');
                });
                draggedRow = null;
            });

            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!draggedRow || draggedRow === this) return;

                const rect = this.getBoundingClientRect();
                const midPoint = rect.top + (rect.height / 2);

                getRows().forEach(r => r.classList.remove('drag-over-top', 'drag-over-bottom'));

                if (e.clientY < midPoint) {
                    this.classList.add('drag-over-top');
                } else {
                    this.classList.add('drag-over-bottom');
                }
            });

            row.addEventListener('dragleave', function () {
                this.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            row.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!draggedRow || draggedRow === this) return;

                const rect = this.getBoundingClientRect();
                const midPoint = rect.top + (rect.height / 2);

                if (e.clientY < midPoint) {
                    tbody.insertBefore(draggedRow, this);
                } else {
                    tbody.insertBefore(draggedRow, this.nextSibling);
                }

                getRows().forEach(r => r.classList.remove('drag-over-top', 'drag-over-bottom'));
                updateOrderNumbers();
                saveReorderedMap();
            });
        });
    });
</script>
@endpush
@endsection
