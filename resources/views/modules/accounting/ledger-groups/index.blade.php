@extends('layouts.duralux')

@section('title', 'Ledger Groups | SaaS ERP')
@section('page-title', 'Ledger Groups')
@section('breadcrumb', 'Accounting / Ledger Groups')

@section('content')

    <div class="row">
        <div class="col-lg-8">
            <x-ui.card title="Ledger Group Directory" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Name</th>
                            <th>Nature</th>
                            <th>Parent</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($ledgerGroups as $ledgerGroup)
                            <tr>
                                <td class="ps-4 fw-bold font-monospace">{{ $ledgerGroup->code }}</td>
                                <td>{{ $ledgerGroup->name }}</td>
                                <td class="text-capitalize">{{ $ledgerGroup->nature }}</td>
                                <td class="text-muted">{{ $ledgerGroup->parent?->name ?: '—' }}</td>
                                <td>
                                    @if ($ledgerGroup->is_active)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.icon-btn type="button" class="edit-ledgergroup-btn" variant="soft-primary" icon="feather-edit" title="Edit Ledger Group"
                                            data-id="{{ $ledgerGroup->id }}"
                                            data-code="{{ $ledgerGroup->code }}"
                                            data-name="{{ $ledgerGroup->name }}"
                                            data-nature="{{ $ledgerGroup->nature }}"
                                            data-parent-id="{{ $ledgerGroup->parent_id }}"
                                            data-is-active="{{ $ledgerGroup->is_active ? '1' : '0' }}" />

                                    <form action="{{ route('accounting.ledger-groups.destroy', $ledgerGroup) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                                data-confirm-title="Delete Ledger Group"
                                                data-confirm-message="Delete ledger group '{{ $ledgerGroup->name }}'?" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No ledger groups configured yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            <x-ui.card title="New Ledger Group" id="ledgerGroupFormCard">
                <form action="{{ route('accounting.ledger-groups.store') }}" method="POST" id="ledgerGroupForm">
                    @csrf
                    <div id="methodContainer"></div>

                    <x-ui.input label="Code" name="code" id="lgCode" required="true" placeholder="e.g. LG-CURASS" />

                    <x-ui.input label="Name" name="name" id="lgName" required="true" placeholder="e.g. Current Assets" />

                    <x-ui.select label="Nature" name="nature" id="lgNature" required="true"
                                 :options="collect(\App\Domains\Accounting\Models\ChartOfAccount::TYPES)->mapWithKeys(fn ($t) => [$t => ucfirst($t)])->all()" />

                    <x-ui.select label="Parent Group" name="parent_id" id="lgParentId"
                                 :options="['' => 'None (top level)'] + $parentOptions->mapWithKeys(fn ($g) => [$g->id => $g->code . ' ' . $g->name])->all()" />

                    <div id="activeField" class="mb-3 row" style="display: none;">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <input type="hidden" name="is_active" value="0">
                            <x-ui.checkbox label="Active" name="is_active" id="lgActive" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <x-ui.button type="button" variant="light" size="sm" class="border" id="resetLgForm" style="display: none;">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" id="lgSubmitBtn">Create Ledger Group</x-ui.button>
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
            $('.edit-ledgergroup-btn').on('click', function() {
                const id = $(this).data('id');
                const code = $(this).data('code');
                const name = $(this).data('name');
                const nature = $(this).data('nature');
                const parentId = $(this).data('parent-id');
                const isActive = $(this).data('is-active');

                $('#ledgerGroupFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Ledger Group');

                $('#ledgerGroupForm').attr('action', `/accounting/ledger-groups/${id}`);
                $('#methodContainer').html('@method("PUT")');

                $('#lgCode').val(code);
                $('#lgName').val(name);
                $('#lgNature').val(nature);
                $('#lgParentId').val(parentId || '');
                $('#lgActive').prop('checked', isActive == 1);

                $('#activeField').slideDown();
                $('#resetLgForm').fadeIn();
                $('#lgSubmitBtn').html('Update Ledger Group');
            });

            $('#resetLgForm').on('click', function() {
                $('#ledgerGroupFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Ledger Group');

                $('#ledgerGroupForm').attr('action', `{{ route('accounting.ledger-groups.store') }}`);
                $('#methodContainer').empty();

                $('#ledgerGroupForm')[0].reset();

                $('#activeField').slideUp();
                $('#resetLgForm').fadeOut();
                $('#lgSubmitBtn').html('Create Ledger Group');
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
