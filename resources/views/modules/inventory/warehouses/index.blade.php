@extends('layouts.duralux')

@section('title', __('inventory.warehouses_master'))
@section('page-title', __('inventory.warehouses'))
@section('breadcrumb', __('inventory.inventory_warehouses'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">{{ __('inventory.success') }}</h6>
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
                    <h6 class="alert-heading fw-bold mb-1">{{ __('inventory.error') }}</h6>
                    <p class="fs-12 mb-0">{{ session('error') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Left: Warehouse List Table -->
        <div class="col-lg-8">
            <x-ui.card :title="__('inventory.warehouse_directory')">
                <div class="table-responsive">
                    <table class="erp-thin-table">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">{{ __('inventory.code') }}</th>
                                <th>{{ __('inventory.name') }}</th>
                                <th>{{ __('inventory.address') }}</th>
                                <th>{{ __('inventory.default') }}</th>
                                <th>{{ __('inventory.status') }}</th>
                                <th class="text-end pe-4">{{ __('inventory.actions') }}</th>
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
                                            <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.default') }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($warehouse->status === 'active')
                                            <span class="erp-badge-active">{{ __('inventory.active') }}</span>
                                        @else
                                            <span class="erp-badge-draft">{{ __('inventory.inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                            <x-ui.icon-btn type="button" class="edit-warehouse-btn" variant="soft-primary" icon="feather-edit" title="{{ __('inventory.edit_warehouse') }}"
                                                    data-id="{{ $warehouse->id }}"
                                                    data-name="{{ $warehouse->name }}"
                                                    data-code="{{ $warehouse->code }}"
                                                    data-address="{{ $warehouse->address }}"
                                                    data-is-default="{{ $warehouse->is_default ? '1' : '0' }}"
                                                    data-status="{{ $warehouse->status }}" />
                                            
                                            <form action="{{ route('inventory.warehouses.destroy', $warehouse) }}" method="POST" onsubmit="return confirm('{{ __('inventory.confirm_delete_warehouse') }}');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn type="submit" variant="soft-danger" icon="feather-trash-2" title="{{ __('inventory.delete') }}" />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="feather-info me-2"></i>{{ __('inventory.no_warehouses_configured') }}
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
            <x-ui.card :title="__('inventory.new_warehouse')" id="warehouseFormCard">
                <form action="{{ route('inventory.warehouses.store') }}" method="POST" id="warehouseForm">
                    @csrf
                    <div id="methodContainer"></div>

                    <!-- Name -->
                    <x-ui.input :label="__('inventory.warehouse_name')" name="name" id="whName" required="true" :placeholder="__('inventory.placeholder_wh_name')" />

                    <!-- Code -->
                    <x-ui.input :label="__('inventory.warehouse_code')" name="code" id="whCode" required="true" :placeholder="__('inventory.placeholder_wh_code')" />

                    <!-- Status (Visible only during Edit) -->
                    <div id="statusField" style="display: none;">
                        <x-ui.select :label="__('inventory.status')" name="status" id="whStatus" :options="['active' => __('inventory.active'), 'inactive' => __('inventory.inactive')]" />
                    </div>

                    <!-- Address -->
                    <x-ui.textarea :label="__('inventory.address')" name="address" id="whAddress" :placeholder="__('inventory.placeholder_wh_address')" rows="4" />

                    <!-- Is Default Checkbox -->
                    <div class="mb-3 row">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" value="1" id="whDefault" class="form-check-input">
                                <label class="form-check-label fs-12 text-dark" for="whDefault">
                                    {{ __('inventory.set_as_default_warehouse') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <button type="button" class="btn btn-sm btn-light border" id="resetWhForm" style="display: none;">{{ __('inventory.cancel') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="whSubmitBtn">{{ __('inventory.create_warehouse') }}</button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const transEditWarehouse = '{{ __('inventory.edit_warehouse') }}';
            const transNewWarehouse = '{{ __('inventory.new_warehouse') }}';
            const transUpdateWarehouse = '{{ __('inventory.update_warehouse') }}';
            const transCreateWarehouse = '{{ __('inventory.create_warehouse') }}';

            // Edit Button Action
            $('.edit-warehouse-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const code = $(this).data('code');
                const address = $(this).data('address');
                const isDefault = $(this).data('is-default');
                const status = $(this).data('status');

                // Update Form Header
                $('#warehouseFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>' + transEditWarehouse);
                
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
                $('#whSubmitBtn').html(transUpdateWarehouse);
            });

            // Cancel Edit Action
            $('#resetWhForm').on('click', function() {
                // Reset Form Header
                $('#warehouseFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>' + transNewWarehouse);
                
                // Reset Form Action and Method
                $('#warehouseForm').attr('action', `{{ route('inventory.warehouses.store') }}`);
                $('#methodContainer').empty();

                // Reset Fields
                $('#warehouseForm')[0].reset();
                
                // Hide Status Field and Cancel Button
                $('#statusField').slideUp();
                $('#resetWhForm').fadeOut();
                $('#whSubmitBtn').html(transCreateWarehouse);
            });
        });
    </script>
@endpush
