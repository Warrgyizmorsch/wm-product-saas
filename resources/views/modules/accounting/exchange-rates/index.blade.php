@extends('layouts.duralux')

@section('title', 'Exchange Rates | SaaS ERP')
@section('page-title', 'Exchange Rates')
@section('breadcrumb', 'Accounting / Exchange Rates')

@php
    use App\Domains\Accounting\Models\ExchangeRate;
    use App\Domains\Accounting\Models\ExchangeRateSyncSetting;

    $formatRate = fn ($rate) => rtrim(rtrim(number_format((float) $rate, 6, '.', ''), '0'), '.');
    $statusVariant = [
        ExchangeRateSyncSetting::STATUS_SUCCESS => 'success',
        ExchangeRateSyncSetting::STATUS_PARTIAL => 'warning',
        ExchangeRateSyncSetting::STATUS_FAILED => 'danger',
        ExchangeRateSyncSetting::STATUS_SKIPPED => 'secondary',
    ];
@endphp

@section('page-actions')
    <x-ui.filter label="Filters">
        <form method="GET">
            <x-ui.select label="Currency" name="currency" :selected="$filters['currency'] ?? ''" :options="
                ['' => 'All Currencies'] + $currencies->mapWithKeys(fn ($currency) => [$currency->code => $currency->code . ' — ' . $currency->name])->all()
            " />
            <x-ui.select label="Source" name="source" :selected="$filters['source'] ?? ''" :options="[
                '' => 'All Sources',
                ExchangeRate::SOURCE_MANUAL => 'Manual',
                ExchangeRate::SOURCE_API => 'Auto-synced',
            ]" />
            <div class="d-flex gap-2">
                <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                <x-ui.button href="{{ route('accounting.exchange-rates.index') }}" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
            </div>
        </form>
    </x-ui.filter>
@endsection

@section('content')

    <div class="row">
        <!-- Left: rate history -->
        <div class="col-lg-8">
            <x-ui.card title="Rate History" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Rate</th>
                            <th>Effective From</th>
                            <th>Source</th>
                            <th>Entered By</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($rates as $rate)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold">1 {{ $rate->from_currency }}</span>
                                    = <span class="fw-bold">{{ $formatRate($rate->rate) }} {{ $rate->to_currency }}</span>
                                </td>
                                <td>{{ $rate->effective_date->format('d M Y') }}</td>
                                <td>
                                    @if ($rate->isSynced())
                                        <x-ui.badge variant="info" soft>Auto</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft>Manual</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $rate->isSynced() ? 'Rate feed' : ($rate->creator?->name ?? '—') }}</td>
                                <td class="text-end pe-4">
                                    @can('update', $rate)
                                        <x-ui.icon-btn type="button" class="edit-rate-btn" variant="soft-primary" icon="feather-edit" title="Edit Rate"
                                                data-id="{{ $rate->id }}"
                                                data-from="{{ $rate->from_currency }}"
                                                data-to="{{ $rate->to_currency }}"
                                                data-rate="{{ $formatRate($rate->rate) }}"
                                                data-date="{{ $rate->effective_date->toDateString() }}"
                                                data-synced="{{ $rate->isSynced() ? '1' : '0' }}" />
                                    @endcan

                                    @can('delete', $rate)
                                        <form action="{{ route('accounting.exchange-rates.destroy', $rate) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                                    data-confirm-title="Delete Exchange Rate"
                                                    data-confirm-message="Delete the {{ $rate->from_currency }} → {{ $rate->to_currency }} rate effective {{ $rate->effective_date->format('d M Y') }}? Journals already posted keep the rate they used." />
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2"></i>No exchange rates yet. Add one, or switch on auto-sync.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>

                @if ($rates->hasPages())
                    <div class="p-3 border-top">{{ $rates->links() }}</div>
                @endif
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            @if ($errors->any())
                <div class="alert alert-danger fs-12 py-2">{{ $errors->first() }}</div>
            @endif

            <!-- Create/Edit form -->
            @can('create', ExchangeRate::class)
                <x-ui.card title="New Exchange Rate" id="rateFormCard">
                    <form action="{{ route('accounting.exchange-rates.store') }}" method="POST" id="rateForm">
                        @csrf
                        <div id="rateMethod"></div>

                        <x-ui.select label="From" name="from_currency" id="rateFrom" required="true" :options="$currencyOptions" :selected="old('from_currency')">
                            <option value="">Select currency…</option>
                        </x-ui.select>
                        <x-ui.select label="To" name="to_currency" id="rateTo" required="true" :options="$currencyOptions" :selected="old('to_currency', $baseCurrencies[0] ?? null)">
                            <option value="">Select currency…</option>
                        </x-ui.select>
                        <x-ui.input label="Rate" name="rate" id="rateValue" type="number" step="any" min="0" required="true"
                                    placeholder="e.g. 2.28" :value="old('rate')" helperText="1 unit of From = Rate units of To." />
                        <x-ui.input label="Effective From" name="effective_date" id="rateDate" type="date" required="true"
                                    :value="old('effective_date', now()->toDateString())" />

                        <div id="rateSyncedNote" class="alert alert-info fs-12 py-2" style="display: none;">
                            This rate came from the feed. Saving it makes it a manual rate, and auto-sync will no longer change it.
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <x-ui.button type="button" variant="light" size="sm" class="border" id="resetRateForm" style="display: none;">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" id="rateSubmitBtn">Add Rate</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endcan

            <!-- Auto-sync -->
            @can('sync', ExchangeRate::class)
                <x-ui.card title="Auto-sync (ECB rates)">
                    <p class="fs-12 text-muted mb-2">
                        Fetches European Central Bank reference rates every working day and stores them against your companies'
                        base {{ \Illuminate\Support\Str::plural('currency', count($baseCurrencies)) }}:
                        <span class="fw-bold text-dark">{{ implode(', ', $baseCurrencies) }}</span>.
                        Manual rates are never overwritten.
                    </p>

                    <form action="{{ route('accounting.exchange-rates.settings') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="is_enabled" value="0">
                        <div class="mb-3">
                            <x-ui.checkbox label="Sync rates automatically every day" name="is_enabled" id="syncEnabled" value="1" :checked="$settings->is_enabled" />
                        </div>

                        <label for="syncCurrencies" class="form-label fw-semibold fs-12 text-uppercase text-dark">Currencies to sync</label>
                        <select name="currencies[]" id="syncCurrencies" class="form-select form-select-sm" multiple size="8">
                            @foreach ($currencies as $currency)
                                @continue(in_array($currency->code, $baseCurrencies, true))
                                <option value="{{ $currency->code }}" @selected(in_array($currency->code, $settings->currencies ?? [], true))>{{ $currency->code }} — {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted fs-11 d-block mb-3">Hold Ctrl (Cmd on Mac) to select several.</small>

                        <div class="d-flex justify-content-end">
                            <x-ui.button type="submit" variant="primary" size="sm">Save Settings</x-ui.button>
                        </div>
                    </form>

                    <hr>

                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="fs-12">
                            @if ($settings->last_synced_at)
                                Last sync {{ $settings->last_synced_at->diffForHumans() }}
                                <x-ui.badge variant="{{ $statusVariant[$settings->last_status] ?? 'secondary' }}" soft>{{ ucfirst($settings->last_status) }}</x-ui.badge>
                            @else
                                <span class="text-muted">Never synced</span>
                            @endif
                        </div>
                        <form action="{{ route('accounting.exchange-rates.sync') }}" method="POST">
                            @csrf
                            <x-ui.button type="submit" variant="light" size="sm" class="border">
                                <i class="feather-refresh-cw me-1"></i>Sync now
                            </x-ui.button>
                        </form>
                    </div>

                    @if (! empty($settings->unsupported))
                        <div class="alert alert-warning fs-12 py-2 mt-3 mb-0">
                            Not published by the ECB — enter these rates manually:
                            <span class="fw-bold">{{ implode(', ', $settings->unsupported) }}</span>
                        </div>
                    @endif

                    @if ($settings->last_error)
                        <div class="alert alert-danger fs-12 py-2 mt-3 mb-0" style="white-space: pre-line;">{{ $settings->last_error }}</div>
                    @endif
                </x-ui.card>
            @endcan
        </div>
    </div>

    <x-ui.confirm-modal />
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            const updateUrlTemplate = @json(route('accounting.exchange-rates.update', ['exchangeRate' => '__ID__']));

            $('.edit-rate-btn').on('click', function () {
                const btn = $(this);

                $('#rateFormCard').find('.card-title').html('<i class="feather-edit me-2 text-primary"></i>Edit Exchange Rate');
                $('#rateForm').attr('action', updateUrlTemplate.replace('__ID__', btn.data('id')));
                $('#rateMethod').html('@method("PUT")');

                $('#rateFrom').val(btn.data('from'));
                $('#rateTo').val(btn.data('to'));
                $('#rateValue').val(btn.data('rate'));
                $('#rateDate').val(btn.data('date'));
                $('#rateSyncedNote').toggle(btn.data('synced') == 1);

                $('#resetRateForm').fadeIn();
                $('#rateSubmitBtn').html('Update Rate');
                document.getElementById('rateFormCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            $('#resetRateForm').on('click', function () {
                $('#rateFormCard').find('.card-title').html('<i class="feather-plus-circle me-2 text-primary"></i>New Exchange Rate');
                $('#rateForm').attr('action', @json(route('accounting.exchange-rates.store')));
                $('#rateMethod').empty();
                $('#rateForm')[0].reset();
                $('#rateSyncedNote').hide();

                $('#resetRateForm').fadeOut();
                $('#rateSubmitBtn').html('Add Rate');
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
