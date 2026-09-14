@extends('layouts.duralux')

@section('title', 'Currencies | SaaS ERP')
@section('page-title', 'Currencies')
@section('breadcrumb', 'Tenant Console / Currencies')

@section('content')

    <div class="row">
        <!-- Left: Currency list -->
        <div class="col-lg-8">
            <x-ui.card title="Currency Master" bodyClass="p-0" class="currency-dense">
                <div class="d-flex flex-wrap align-items-center gap-3 p-3 border-bottom">
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 220px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" id="curSearchInput" class="form-control border-0 bg-transparent p-0 fs-13"
                               placeholder="Search code or name..." style="box-shadow: none; height: 32px;">
                    </div>
                    <select id="curStatusFilter" class="form-select form-select-sm" style="max-width: 140px;">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <span class="ms-auto fs-12 text-muted">{{ $currencies->where('is_active', true)->count() }} active of {{ $currencies->count() }}</span>
                </div>

                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Name</th>
                            <th>Symbol</th>
                            <th class="text-end">Decimals</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark" id="curTableBody">
                        @forelse ($currencies as $currency)
                            <tr data-cur-search="{{ strtolower($currency->code . ' ' . $currency->name) }}" data-cur-status="{{ $currency->is_active ? 'active' : 'inactive' }}">
                                <td class="ps-4 fw-bold font-monospace">{{ $currency->code }}</td>
                                <td>{{ $currency->name }}</td>
                                <td>{{ $currency->symbol }}</td>
                                <td class="text-end">{{ $currency->decimals }}</td>
                                <td>
                                    @if ($currency->is_active)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.icon-btn type="button" class="edit-currency-btn" variant="soft-primary" icon="feather-edit" title="Edit Currency"
                                            data-id="{{ $currency->id }}"
                                            data-code="{{ $currency->code }}"
                                            data-name="{{ $currency->name }}"
                                            data-symbol="{{ $currency->symbol }}"
                                            data-decimals="{{ $currency->decimals }}" />

                                    <form action="{{ route('platform.currencies.status', $currency) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <x-ui.icon-btn type="button"
                                                variant="{{ $currency->is_active ? 'soft-danger' : 'soft-success' }}"
                                                icon="{{ $currency->is_active ? 'feather-slash' : 'feather-check-circle' }}"
                                                title="{{ $currency->is_active ? 'Deactivate' : 'Activate' }}"
                                                data-confirm-title="{{ $currency->is_active ? 'Deactivate' : 'Activate' }} Currency"
                                                data-confirm-message="{{ $currency->is_active
                                                    ? "Deactivate {$currency->code}? It will no longer be selectable, but existing companies, journals and exchange rates keep it."
                                                    : "Activate {$currency->code}?" }}" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No currencies yet. Run the CurrencySeeder or add one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        <!-- Right: Create/Edit form -->
        <div class="col-lg-4">
            <x-ui.card title="New Currency" id="currencyFormCard">
                @if ($errors->any())
                    <div class="alert alert-danger fs-12 py-2">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('platform.currencies.store') }}" method="POST" id="currencyForm">
                    @csrf
                    <div id="currencyMethod"></div>

                    <x-ui.input label="Code" name="code" id="curCode" required="true" placeholder="e.g. GBP" maxlength="3"
                                :value="old('code')" helperText="ISO 4217 code. Cannot be changed after creation." />
                    <x-ui.input label="Name" name="name" id="curName" required="true" placeholder="e.g. Pound Sterling" :value="old('name')" />
                    <x-ui.input label="Symbol" name="symbol" id="curSymbol" required="true" placeholder="e.g. £" :value="old('symbol')" />
                    <x-ui.input label="Decimals" name="decimals" id="curDecimals" type="number" min="0" max="4" required="true"
                                :value="old('decimals', 2)" helperText="Minor units: 2 for USD, 0 for JPY, 3 for KWD." />

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <x-ui.button type="button" variant="light" size="sm" class="border" id="resetCurForm" style="display: none;">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" id="curSubmitBtn">Create Currency</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>

    <x-ui.confirm-modal />
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const updateUrlTemplate = @json(route('platform.currencies.update', ['currency' => '__ID__']));

            $('.edit-currency-btn').on('click', function () {
                const btn = $(this);

                $('#currencyFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Currency');
                $('#currencyForm').attr('action', updateUrlTemplate.replace('__ID__', btn.data('id')));
                $('#currencyMethod').html('@method("PUT")');

                $('#curCode').val(btn.data('code')).prop('readonly', true);
                $('#curName').val(btn.data('name'));
                $('#curSymbol').val(btn.data('symbol'));
                $('#curDecimals').val(btn.data('decimals'));

                $('#resetCurForm').fadeIn();
                $('#curSubmitBtn').html('Update Currency');
            });

            $('#resetCurForm').on('click', function () {
                $('#currencyFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Currency');
                $('#currencyForm').attr('action', @json(route('platform.currencies.store')));
                $('#currencyMethod').empty();
                $('#currencyForm')[0].reset();
                $('#curCode').prop('readonly', false);

                $('#resetCurForm').fadeOut();
                $('#curSubmitBtn').html('Create Currency');
            });

            function applyCurFilters() {
                const term = $('#curSearchInput').val().trim().toLowerCase();
                const status = $('#curStatusFilter').val();

                $('#curTableBody tr[data-cur-search]').each(function () {
                    const row = $(this);
                    const matchesTerm = term === '' || row.data('cur-search').includes(term);
                    const matchesStatus = status === '' || row.data('cur-status') === status;
                    row.toggle(matchesTerm && matchesStatus);
                });
            }

            $('#curSearchInput').on('input', applyCurFilters);
            $('#curStatusFilter').on('change', applyCurFilters);
        });
    </script>
@endpush

@push('styles')
    <style>
        .currency-dense table th,
        .currency-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
