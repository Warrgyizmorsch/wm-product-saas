@extends('layouts.duralux')

@section('title', 'TDS Setup | SaaS ERP')
@section('page-title', 'TDS Setup')
@section('breadcrumb', 'Accounting / TDS / Setup')

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <x-ui.card title="TDS sections (nature of payment)" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Section</th>
                            <th class="text-end">Ind./HUF</th>
                            <th class="text-end">Others</th>
                            <th class="text-end">No PAN</th>
                            <th>Threshold</th>
                            <th>Ledger</th>
                            <th>Status</th>
                            @if ($canConfigure)<th class="text-end pe-4"></th>@endif
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @foreach ($sections as $section)
                            <tr>
                                <td class="ps-4"><strong>{{ $section->code }}</strong> {{ $section->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($section->rate_individual, 3), '0'), '.') }}%</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($section->rate_other, 3), '0'), '.') }}%</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($section->rate_no_pan, 3), '0'), '.') }}%</td>
                                <td class="text-muted">
                                    @if ($section->single_threshold) {{ format_currency($section->single_threshold) }} a bill @endif
                                    @if ($section->single_threshold && $section->aggregate_threshold) / @endif
                                    @if ($section->aggregate_threshold) {{ format_currency($section->aggregate_threshold) }} {{ $section->threshold_period === 'monthly' ? 'a month' : 'a year' }}@endif
                                    @if ($section->deduct_on_excess_only) <span class="small">(on excess)</span>@endif
                                </td>
                                <td class="text-muted">{{ $section->payableAccount ? $section->payableAccount->code . ' ' . $section->payableAccount->name : '—' }}</td>
                                <td>
                                    @if ($section->is_active)
                                        <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                    @endif
                                </td>
                                @if ($canConfigure)
                                    <td class="text-end pe-4">
                                        <x-ui.icon-btn type="button" class="edit-section-btn" variant="soft-primary" icon="feather-edit" title="Edit section"
                                            data-section="{{ json_encode($section->only(['id', 'code', 'name', 'nature_of_payment', 'rate_individual', 'rate_other', 'rate_no_pan', 'single_threshold', 'aggregate_threshold', 'threshold_period', 'deduct_on_excess_only', 'is_active'])) }}" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>

            @if ($canConfigure)
                <x-ui.card title="Edit section" id="sectionFormCard" style="display: none;">
                    <form method="POST" id="sectionForm">
                        @csrf
                        @method('PUT')
                        <div class="row g-2">
                            <div class="col-md-6"><x-ui.input label="Name" name="name" id="secName" required="true" /></div>
                            <div class="col-md-6"><x-ui.input label="Nature of payment" name="nature_of_payment" id="secNature" /></div>
                            <div class="col-md-4"><x-ui.input label="Rate - Individual/HUF (%)" name="rate_individual" id="secRateInd" type="number" step="0.001" required="true" /></div>
                            <div class="col-md-4"><x-ui.input label="Rate - Others (%)" name="rate_other" id="secRateOther" type="number" step="0.001" required="true" /></div>
                            <div class="col-md-4"><x-ui.input label="Rate - No PAN (%)" name="rate_no_pan" id="secRateNoPan" type="number" step="0.001" required="true" /></div>
                            <div class="col-md-4"><x-ui.input label="Single bill limit" name="single_threshold" id="secSingle" type="number" step="0.01" /></div>
                            <div class="col-md-4"><x-ui.input label="Aggregate limit" name="aggregate_threshold" id="secAggregate" type="number" step="0.01" /></div>
                            <div class="col-md-4">
                                <x-ui.select label="Limit applies" name="threshold_period" id="secPeriod" :options="['per_transaction' => 'Per bill only', 'annual' => 'Per financial year', 'monthly' => 'Per month (rent)']" />
                            </div>
                        </div>
                        <input type="hidden" name="deduct_on_excess_only" value="0">
                        <x-ui.checkbox label="Deduct only on the amount above the yearly limit (194Q)" name="deduct_on_excess_only" id="secExcess" />
                        <input type="hidden" name="is_active" value="0">
                        <x-ui.checkbox label="Active" name="is_active" id="secActive" />
                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <x-ui.button type="button" variant="light" size="sm" class="border" id="cancelSection">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">Save section</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <div class="col-lg-4">
            <x-ui.card title="Deductor details (26Q / Form 16A)">
                <form action="{{ route('accounting.tds.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <fieldset @disabled(! $canConfigure)>
                        <x-ui.input label="TAN" name="tan" :value="old('tan', $config->tan)" placeholder="ABCD12345E" />
                        <x-ui.input label="Deductor name" name="deductor_name" :value="old('deductor_name', $config->deductor_name)" />
                        <x-ui.select label="Deductor type" name="deductor_type" :options="['company' => 'Company', 'firm' => 'Firm / LLP', 'individual' => 'Individual / HUF', 'aop' => 'AOP / BOI', 'government' => 'Government']" :selected="old('deductor_type', $config->deductor_type)" />
                        <x-ui.input label="Deductor PAN" name="deductor_pan" :value="old('deductor_pan', $config->deductor_pan)" />
                        <x-ui.input label="Responsible person" name="responsible_person_name" :value="old('responsible_person_name', $config->responsible_person_name)" />
                        <x-ui.input label="Designation" name="responsible_person_designation" :value="old('responsible_person_designation', $config->responsible_person_designation)" />
                        <x-ui.input label="Responsible person PAN" name="responsible_person_pan" :value="old('responsible_person_pan', $config->responsible_person_pan)" />
                        <x-ui.input label="Address" name="address" :value="old('address', $config->address)" />
                        <x-ui.input label="City" name="city" :value="old('city', $config->city)" />
                        <x-ui.input label="State" name="state" :value="old('state', $config->state)" />
                        <x-ui.input label="PIN code" name="pincode" :value="old('pincode', $config->pincode)" />
                        <x-ui.input label="Email" name="email" type="email" :value="old('email', $config->email)" />
                        <x-ui.input label="Phone" name="phone" :value="old('phone', $config->phone)" />
                        @if ($canConfigure)
                            <div class="text-end mt-3"><x-ui.button type="submit" variant="primary" size="sm">Save details</x-ui.button></div>
                        @endif
                    </fieldset>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('.edit-section-btn').on('click', function () {
                const s = $(this).data('section');
                $('#sectionForm').attr('action', '{{ url('accounting/tds/sections') }}/' + s.id);
                $('#sectionFormCard .card-title').text('Edit section ' + s.code);
                $('#secName').val(s.name);
                $('#secNature').val(s.nature_of_payment);
                $('#secRateInd').val(s.rate_individual);
                $('#secRateOther').val(s.rate_other);
                $('#secRateNoPan').val(s.rate_no_pan);
                $('#secSingle').val(s.single_threshold);
                $('#secAggregate').val(s.aggregate_threshold);
                $('#secPeriod').val(s.threshold_period);
                $('#secExcess').prop('checked', !!s.deduct_on_excess_only);
                $('#secActive').prop('checked', !!s.is_active);
                $('#sectionFormCard').slideDown();
                $('html, body').animate({ scrollTop: $('#sectionFormCard').offset().top - 80 }, 200);
            });
            $('#cancelSection').on('click', () => $('#sectionFormCard').slideUp());
        });
    </script>
@endpush

@push('styles')
    <style>
        .accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; }
    </style>
@endpush
