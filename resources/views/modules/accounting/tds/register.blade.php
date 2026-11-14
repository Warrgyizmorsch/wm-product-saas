@extends('layouts.duralux')

@section('title', 'TDS Register | SaaS ERP')
@section('page-title', 'TDS Register & Challans')
@section('breadcrumb', 'Accounting / TDS / Register')

@include('modules.accounting.tds._filter-styles')

@section('content')
    @if ($errors->has('challan'))
        <div class="alert alert-danger">{{ $errors->first('challan') }}</div>
    @endif

    <x-ui.card bodyClass="p-3" class="mb-3">
        <form method="GET" class="tds-filters">
            <div class="tds-filter">
                <label for="tdsFy">Financial year</label>
                <select name="fy" id="tdsFy" class="form-select" data-no-search>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected($year === $fy)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tds-filter">
                <label for="tdsQuarter">Quarter</label>
                <select name="quarter" id="tdsQuarter" class="form-select" data-no-search>
                    <option value="">All quarters</option>
                    @foreach (\App\Domains\Accounting\Services\Tds\TdsReturnService::QUARTERS as $q => $label)
                        <option value="{{ $q }}" @selected($quarter === $q)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tds-filter tds-filter-wide">
                <label for="tdsSection">Section</label>
                <select name="section" id="tdsSection" class="form-select" data-no-search>
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((string) request('section') === (string) $section->id)>{{ $section->code }} - {{ $section->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tds-filter">
                <label for="tdsStatus">Status</label>
                <select name="status" id="tdsStatus" class="form-select" data-no-search>
                    @foreach (['all' => 'All', 'undeposited' => 'Not deposited', 'deposited' => 'Deposited', 'below_threshold' => 'Below threshold'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="primary">Show</x-ui.button>
            <x-ui.button href="{{ route('accounting.tds.register') }}" variant="light" class="border" icon="feather-rotate-ccw">Reset</x-ui.button>
            <div class="tds-filter-summary">
                <div class="fs-11 text-uppercase text-muted fw-bold">TDS deducted, not yet deposited</div>
                <div class="fs-5 fw-bold text-danger">{{ format_currency($undepositedTotal) }}</div>
            </div>
        </form>
    </x-ui.card>

    <form action="{{ route('accounting.tds.challans.store') }}" method="POST" id="challanForm">
        @csrf
        <x-ui.card title="Deductions" bodyClass="p-0" class="accounting-dense mb-3">
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        @if ($canDeposit)<th class="ps-3"><input type="checkbox" class="form-check-input" id="tdsCheckAll"></th>@endif
                        <th>Date</th>
                        <th>Document</th>
                        <th>Party</th>
                        <th>PAN</th>
                        <th>Section</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">TDS</th>
                        <th>Challan</th>
                    </tr>
                </thead>
                <tbody class="fs-13">
                    @forelse ($deductions as $d)
                        @php($open = $d->tds_amount > 0 && ! $d->tds_challan_id)
                        <tr @class(['text-muted' => $d->reason === 'below_threshold'])>
                            @if ($canDeposit)
                                <td class="ps-3">
                                    @if ($open)
                                        <input type="checkbox" class="form-check-input tds-pick" name="deduction_ids[]" value="{{ $d->id }}" data-amount="{{ $d->tds_amount }}" @checked(in_array($d->id, old('deduction_ids', [])))>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $d->deduction_date->format('d M Y') }}</td>
                            <td>
                                @if ($d->source_type === 'vendor_bill')
                                    <a href="{{ route('purchase.bills.show', $d->source_id) }}">{{ $d->source_number }}</a>
                                @else
                                    {{ $d->source_number }}
                                @endif
                            </td>
                            <td>{{ $d->party_name }}</td>
                            <td>{!! $d->pan ?: '<span class="text-danger">None</span>' !!}</td>
                            <td>{{ $d->section?->code }}</td>
                            <td class="text-end">
                                {{ format_currency($d->base_amount) }}
                                @if ($d->catchup_base > 0)<div class="small text-muted">+ {{ format_currency($d->catchup_base) }} earlier</div>@endif
                            </td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($d->rate, 3), '0'), '.') }}%</td>
                            <td class="text-end fw-semibold">
                                {{ format_currency($d->tds_amount) }}
                                @if ($d->reason === 'below_threshold')<div class="small">below threshold</div>
                                @elseif ($d->reason === 'no_pan')<div class="small text-danger">no PAN</div>
                                @elseif ($d->reason === 'lower_cert')<div class="small">lower cert.</div>@endif
                            </td>
                            <td>
                                @if ($d->challan)
                                    {{ $d->challan->challan_number }} <span class="text-muted small">{{ $d->challan->deposit_date->format('d M') }}</span>
                                @elseif ($open)
                                    <x-ui.badge variant="warning" soft>Due</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">No TDS entries for this selection.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <div class="p-2">{{ $deductions->links() }}</div>
        </x-ui.card>

        @if ($canDeposit)
            <x-ui.card title="Deposit challan (ITNS 281)" class="mb-3">
                <p class="text-muted fs-13">Tick the deductions above that this challan pays. Selected: <strong id="tdsPickedTotal">{{ format_currency(0) }}</strong></p>
                <div class="row g-2">
                    <div class="col-md-3"><x-ui.input label="Challan serial no." name="challan_number" :value="old('challan_number')" required="true" stacked /></div>
                    <div class="col-md-3"><x-ui.input label="BSR code" name="bsr_code" :value="old('bsr_code')" placeholder="7 digits" stacked /></div>
                    <div class="col-md-3"><x-ui.input label="Deposit date" name="deposit_date" type="date" :value="old('deposit_date', now()->toDateString())" required="true" stacked /></div>
                    <div class="col-md-3">
                        <x-ui.select label="Paid from" name="bank_account_id" :options="$bankAccounts->mapWithKeys(fn ($a) => [$a->id => $a->code . ' ' . $a->name])->all()" :selected="old('bank_account_id')" required="true" stacked />
                    </div>
                    <div class="col-md-3"><x-ui.input label="Interest" name="interest" type="number" step="0.01" :value="old('interest', 0)" stacked /></div>
                    <div class="col-md-3"><x-ui.input label="Late fee (234E)" name="late_fee" type="number" step="0.01" :value="old('late_fee', 0)" stacked /></div>
                    <div class="col-md-6"><x-ui.input label="Notes" name="notes" :value="old('notes')" stacked /></div>
                </div>
                <div class="form-check mt-2">
                    <input type="hidden" name="already_paid" value="0">
                    <input class="form-check-input" type="checkbox" name="already_paid" value="1" id="tdsAlreadyPaid" @checked(old('already_paid'))>
                    <label class="form-check-label fs-13" for="tdsAlreadyPaid">
                        Already paid from <strong>Payroll &gt; Statutory dues</strong> - only record the challan details, don't post the payment again
                    </label>
                </div>
                <div class="text-end mt-2"><x-ui.button type="submit" variant="primary" size="sm">Record challan & post</x-ui.button></div>
            </x-ui.card>
        @endif
    </form>

    <x-ui.card title="Recent challans" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr><th class="ps-3">Deposited</th><th>Challan</th><th>BSR</th><th>Bank</th><th class="text-end">Tax</th><th class="text-end">Interest + fee</th><th class="text-end pe-3">Total</th></tr>
            </thead>
            <tbody class="fs-13">
                @forelse ($challans as $c)
                    <tr>
                        <td class="ps-3">{{ $c->deposit_date->format('d M Y') }}</td>
                        <td>{{ $c->challan_number }}</td>
                        <td>{{ $c->bsr_code }}</td>
                        <td>{{ $c->bankAccount?->name }}</td>
                        <td class="text-end">{{ format_currency($c->tax_amount) }}</td>
                        <td class="text-end">{{ format_currency($c->interest + $c->late_fee) }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ format_currency($c->totalAmount()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-3 text-muted">No challans yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        $(function () {
            const fmt = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            function total() {
                let sum = 0;
                $('.tds-pick:checked').each(function () { sum += parseFloat($(this).data('amount')) || 0; });
                $('#tdsPickedTotal').text(fmt.format(sum));
            }
            $('#tdsCheckAll').on('change', function () { $('.tds-pick').prop('checked', this.checked); total(); });
            $(document).on('change', '.tds-pick', total);
            total();
        });
    </script>
@endpush

@push('styles')
    <style>
        .accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; }
    </style>
@endpush
