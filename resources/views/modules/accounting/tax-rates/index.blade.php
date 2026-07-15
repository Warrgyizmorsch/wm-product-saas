@extends('layouts.duralux')

@section('title', 'Tax Rates | SaaS ERP')
@section('page-title', 'Tax Rates')
@section('breadcrumb', 'Accounting / Tax Rates')

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    <div class="row">
        <!-- Left: Tax Rate List Table -->
        <div class="col-lg-8">
            <x-ui.card title="Tax Rate Directory" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Type</th>
                            <th class="text-end">Rate</th>
                            <th>Compound</th>
                            <th>Payable Account</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($taxRates as $taxRate)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $taxRate->name }}</td>
                                <td class="text-uppercase">{{ $taxRate->type }}</td>
                                <td class="text-end">{{ number_format($taxRate->rate, 2) }}%</td>
                                <td>
                                    @if ($taxRate->is_compound)
                                        <x-ui.badge variant="info" soft>Compound</x-ui.badge>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $taxRate->taxPayableAccount?->name ?: '—' }}</td>
                                <td>
                                    @if ($taxRate->is_active)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.icon-btn type="button" class="edit-taxrate-btn" variant="soft-primary" icon="feather-edit" title="Edit Tax Rate"
                                            data-id="{{ $taxRate->id }}"
                                            data-name="{{ $taxRate->name }}"
                                            data-type="{{ $taxRate->type }}"
                                            data-rate="{{ $taxRate->rate }}"
                                            data-is-compound="{{ $taxRate->is_compound ? '1' : '0' }}"
                                            data-payable-account-id="{{ $taxRate->tax_payable_account_id }}"
                                            data-is-active="{{ $taxRate->is_active ? '1' : '0' }}" />

                                    <form action="{{ route('accounting.tax-rates.destroy', $taxRate) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                                data-confirm-title="Delete Tax Rate"
                                                data-confirm-message="Delete tax rate '{{ $taxRate->name }}'?" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No tax rates configured yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        <!-- Right: Create/Edit Form Card -->
        <div class="col-lg-4">
            <x-ui.card title="New Tax Rate" id="taxRateFormCard">
                <form action="{{ route('accounting.tax-rates.store') }}" method="POST" id="taxRateForm">
                    @csrf
                    <div id="methodContainer"></div>

                    <x-ui.input label="Name" name="name" id="trName" required="true" placeholder="e.g. GST 18%" />

                    <x-ui.select label="Type" name="type" id="trType" :options="[
                        'sales_tax' => 'Sales Tax',
                        'gst' => 'GST',
                        'vat' => 'VAT',
                        'withholding' => 'Withholding',
                    ]" />

                    <x-ui.input label="Rate (%)" name="rate" id="trRate" type="number" required="true" placeholder="e.g. 18" />

                    <x-ui.select label="Payable Account" name="tax_payable_account_id" id="trPayableAccount" :options="$payableAccounts->pluck('name', 'id')->all()" helperText="Optional — the liability account this tax is credited to." />

                    <div class="mb-3 row">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <input type="hidden" name="is_compound" value="0">
                            <x-ui.checkbox label="Compound tax" name="is_compound" id="trCompound" />
                        </div>
                    </div>

                    <div id="activeField" class="mb-3 row" style="display: none;">
                        <div class="col-md-4"></div>
                        <div class="col-md-8">
                            <input type="hidden" name="is_active" value="0">
                            <x-ui.checkbox label="Active" name="is_active" id="trActive" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <x-ui.button type="button" variant="light" size="sm" class="border" id="resetTrForm" style="display: none;">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" id="trSubmitBtn">Create Tax Rate</x-ui.button>
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
            $('.edit-taxrate-btn').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const type = $(this).data('type');
                const rate = $(this).data('rate');
                const isCompound = $(this).data('is-compound');
                const payableAccountId = $(this).data('payable-account-id');
                const isActive = $(this).data('is-active');

                $('#taxRateFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Tax Rate');

                $('#taxRateForm').attr('action', `/accounting/tax-rates/${id}`);
                $('#methodContainer').html('@method("PUT")');

                $('#trName').val(name);
                $('#trType').val(type);
                $('#trRate').val(rate);
                $('#trCompound').prop('checked', isCompound == 1);
                $('#trPayableAccount').val(payableAccountId);
                $('#trActive').prop('checked', isActive == 1);

                $('#activeField').slideDown();
                $('#resetTrForm').fadeIn();
                $('#trSubmitBtn').html('Update Tax Rate');
            });

            $('#resetTrForm').on('click', function() {
                $('#taxRateFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Tax Rate');

                $('#taxRateForm').attr('action', `{{ route('accounting.tax-rates.store') }}`);
                $('#methodContainer').empty();

                $('#taxRateForm')[0].reset();

                $('#activeField').slideUp();
                $('#resetTrForm').fadeOut();
                $('#trSubmitBtn').html('Create Tax Rate');
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
