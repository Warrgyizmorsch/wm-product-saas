@extends('layouts.duralux')

@section('title', 'Warehouses Master | SaaS ERP')
@section('page-title', 'Warehouses')
@section('breadcrumb', 'Inventory / Warehouses')

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Left: Warehouse List Table -->
        <div class="col-lg-8">
            <x-ui.card title="Warehouse Directory">
                <div class="table-responsive">
                    <table class="erp-thin-table">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Default</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($warehouses as $warehouse)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">
                                        {{ $warehouse->code }}
                                    </td>
                                    <td class="fw-bold">{{ $warehouse->name }}</td>
                                    <td class="text-muted text-truncate" style="max-width: 200px;" title="{{ $warehouse->address }}">
                                        {{ $warehouse->address ?: '—' }}
                                    </td>
                                    <td>
                                        @if ($warehouse->is_default)
                                            <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 fw-semibold">Default</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($warehouse->status === 'active')
                                            <span class="erp-badge-active">Active</span>
                                        @else
                                            <span class="erp-badge-draft">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                            <x-ui.icon-btn type="button" class="edit-warehouse-btn" variant="soft-primary" icon="feather-edit" title="Edit Warehouse"
                                                    data-id="{{ $warehouse->id }}"
                                                    data-name="{{ $warehouse->name }}"
                                                    data-code="{{ $warehouse->code }}"
                                                    data-address="{{ $warehouse->address }}"
                                                    data-is-default="{{ $warehouse->is_default ? '1' : '0' }}"
                                                    data-status="{{ $warehouse->status }}" />
                                            
                                            <form action="{{ route('inventory.warehouses.destroy', $warehouse) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this warehouse? All stock associations will be deleted.');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="Delete" />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>No warehouses configured. Set up your first location.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

        <!-- Right: Create/Edit Form Card -->
        <div class="col-lg-4">
            <x-ui.card title="New Warehouse" id="warehouseFormCard">
                <form action="{{ route('inventory.warehouses.store') }}" method="POST" id="warehouseForm">
                    @csrf
                    <div id="methodContainer"></div>

                    <!-- Name -->
                    <x-ui.input label="Warehouse Name" name="name" id="whName" required="true" placeholder="e.g. Main Warehouse" />

                    <!-- Code -->
                    <x-ui.input label="Warehouse Code" name="code" id="whCode" required="true" placeholder="e.g. WH-MAIN" />

                    <!-- Status (Visible only during Edit) -->
                    <div id="statusField" style="display: none;">
                        <x-ui.select label="Status" name="status" id="whStatus" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
                    </div>

                    <!-- Address -->
                    <x-ui.textarea label="Address" name="address" id="whAddress" placeholder="Warehouse physical address..." rows="4" />

                    <!-- Is Default Checkbox -->
                    <div class="mb-3 row">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" value="1" id="whDefault" class="form-check-input">
                                <label class="form-check-label fs-12 text-dark" for="whDefault">
                                    Set as default warehouse
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-sm btn-light border" id="resetWhForm" style="display: none;">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="whSubmitBtn">Create Warehouse</button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Edit Button Action
            $('.edit-warehouse-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const code = $(this).data('code');
                const address = $(this).data('address');
                const isDefault = $(this).data('is-default');
                const status = $(this).data('status');

                // Update Form Header
                $('#warehouseFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Warehouse');
                
                // Update Form Action and Method
                $('#warehouseForm').attr('action', `/inventory/warehouses/${id}`);
                $('#methodContainer').html('@method("PUT")');

                // Populate Fields
                $('#whName').val(name);
                $('#whCode').val(code);
                $('#whAddress').val(address);
                $('#whDefault').prop('checked', isDefault == 1);
                $('#whStatus').val(status);
                
                // Show Status Field and Cancel Button
                $('#statusField').slideDown();
                $('#resetWhForm').fadeIn();
                $('#whSubmitBtn').html('Update Warehouse');
            });

            // Cancel Edit Action
            $('#resetWhForm').on('click', function() {
                // Reset Form Header
                $('#warehouseFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Warehouse');
                
                // Reset Form Action and Method
                $('#warehouseForm').attr('action', `{{ route('inventory.warehouses.store') }}`);
                $('#methodContainer').empty();

                // Reset Fields
                $('#warehouseForm')[0].reset();
                
                // Hide Status Field and Cancel Button
                $('#statusField').slideUp();
                $('#resetWhForm').fadeOut();
                $('#whSubmitBtn').html('Create Warehouse');
            });
        });
    </script>
@endpush
