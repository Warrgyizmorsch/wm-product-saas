@extends('layouts.duralux')

@section('title', 'Cost Centers | SaaS ERP')
@section('page-title', 'Cost Centers')
@section('breadcrumb', 'Accounting / Cost Centers')

@section('content')

    <div class="row">
        <div class="col-lg-8">
            <x-ui.card title="Cost Center Directory" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($costCenters as $costCenter)
                            <tr>
                                <td class="ps-4 fw-bold font-monospace">{{ $costCenter->code }}</td>
                                <td>{{ $costCenter->name }}</td>
                                <td class="text-muted">{{ $costCenter->description ?: '—' }}</td>
                                <td>
                                    @if ($costCenter->is_active)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.icon-btn type="button" class="edit-costcenter-btn" variant="soft-primary" icon="feather-edit" title="Edit Cost Center"
                                            data-id="{{ $costCenter->id }}"
                                            data-code="{{ $costCenter->code }}"
                                            data-name="{{ $costCenter->name }}"
                                            data-description="{{ $costCenter->description }}"
                                            data-is-active="{{ $costCenter->is_active ? '1' : '0' }}" />

                                    <form action="{{ route('accounting.cost-centers.destroy', $costCenter) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                                data-confirm-title="Delete Cost Center"
                                                data-confirm-message="Delete cost center '{{ $costCenter->name }}'?" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No cost centers configured yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            <x-ui.card title="New Cost Center" id="costCenterFormCard">
                <form action="{{ route('accounting.cost-centers.store') }}" method="POST" id="costCenterForm">
                    @csrf
                    <div id="methodContainer"></div>

                    <x-ui.input label="Code" name="code" id="ccCode" required="true" placeholder="e.g. CC-SALES" />

                    <x-ui.input label="Name" name="name" id="ccName" required="true" placeholder="e.g. Sales Department" />

                    <x-ui.input label="Description" name="description" id="ccDescription" placeholder="Optional" />

                    <div id="activeField" class="mb-3 row" style="display: none;">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <input type="hidden" name="is_active" value="0">
                            <x-ui.checkbox label="Active" name="is_active" id="ccActive" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <x-ui.button type="button" variant="light" size="sm" class="border" id="resetCcForm" style="display: none;">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" id="ccSubmitBtn">Create Cost Center</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>

    <x-ui.confirm-modal />
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.edit-costcenter-btn').on('click', function() {
                const id = $(this).data('id');
                const code = $(this).data('code');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const isActive = $(this).data('is-active');

                $('#costCenterFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Cost Center');

                $('#costCenterForm').attr('action', `/accounting/cost-centers/${id}`);
                $('#methodContainer').html('@method("PUT")');

                $('#ccCode').val(code);
                $('#ccName').val(name);
                $('#ccDescription').val(description);
                $('#ccActive').prop('checked', isActive == 1);

                $('#activeField').slideDown();
                $('#resetCcForm').fadeIn();
                $('#ccSubmitBtn').html('Update Cost Center');
            });

            $('#resetCcForm').on('click', function() {
                $('#costCenterFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Cost Center');

                $('#costCenterForm').attr('action', `{{ route('accounting.cost-centers.store') }}`);
                $('#methodContainer').empty();

                $('#costCenterForm')[0].reset();

                $('#activeField').slideUp();
                $('#resetCcForm').fadeOut();
                $('#ccSubmitBtn').html('Create Cost Center');
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
