@extends('layouts.duralux')

@section('title', 'TDS Returns | SaaS ERP')
@section('page-title', 'TDS Returns (26Q / 24Q)')
@section('breadcrumb', 'Accounting / TDS / Returns')

@php($quarters = \App\Domains\Accounting\Services\Tds\TdsReturnService::QUARTERS)
@php($isSalary = $form === \App\Domains\Accounting\Services\Tds\TdsReturnService::FORM_24Q)
@php($periodQuery = ['form' => $form, 'fy' => $fiscal_year, 'quarter' => $quarter])
@include('modules.accounting.tds._filter-styles')

@section('content')
    <ul class="nav nav-tabs mb-3">
        @foreach (\App\Domains\Accounting\Services\Tds\TdsReturnService::FORMS as $code => $label)
            <li class="nav-item">
                <a class="nav-link @if ($form === $code) active fw-semibold @endif" href="{{ route('accounting.tds.returns', ['form' => $code, 'fy' => $fiscal_year, 'quarter' => $quarter]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <x-ui.card bodyClass="p-3" class="mb-3">
        <form method="GET" class="tds-filters">
            <input type="hidden" name="form" value="{{ $form }}">
            <div class="tds-filter">
                <label for="tdsFy">Financial year</label>
                <select name="fy" id="tdsFy" class="form-select" data-no-search>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected($year === $fiscal_year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tds-filter">
                <label for="tdsQuarter">Quarter</label>
                <select name="quarter" id="tdsQuarter" class="form-select" data-no-search>
                    @foreach ($quarters as $q => $label)
                        <option value="{{ $q }}" @selected($quarter === $q)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="primary">Show</x-ui.button>
            <x-ui.button href="{{ route('accounting.tds.returns', ['form' => $form]) }}" variant="light" class="border" icon="feather-rotate-ccw">Reset</x-ui.button>
            @if ($canExport)
                <div class="tds-filter-actions">
                    <x-ui.button href="{{ route('accounting.tds.returns.export', ['format' => 'xlsx', ...$periodQuery]) }}" variant="light" class="border" icon="feather-download">{{ $form }} Excel</x-ui.button>
                    <x-ui.button href="{{ route('accounting.tds.returns.export', ['format' => 'pdf', ...$periodQuery]) }}" variant="light" class="border" icon="feather-file-text">{{ $form }} PDF</x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.card>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="p-3 border rounded"><div class="fs-11 text-uppercase text-muted fw-bold">{{ $isSalary ? 'Salary paid' : 'Amount paid / credited' }}</div><div class="fs-5 fw-bold">{{ format_currency($totals['base']) }}</div></div></div>
        <div class="col-md-4"><div class="p-3 border rounded"><div class="fs-11 text-uppercase text-muted fw-bold">TDS deducted</div><div class="fs-5 fw-bold">{{ format_currency($totals['tds']) }}</div></div></div>
        <div class="col-md-4"><div class="p-3 border rounded"><div class="fs-11 text-uppercase text-muted fw-bold">TDS deposited</div><div class="fs-5 fw-bold {{ $totals['deposited'] < $totals['tds'] ? 'text-danger' : 'text-success' }}">{{ format_currency($totals['deposited']) }}</div></div></div>
    </div>

    @if ($issues)
        <div class="alert alert-warning">
            <strong>Fix before filing:</strong>
            <ul class="mb-0">@foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <x-ui.card title="{{ $isSalary ? 'Salary TDS (Annexure I)' : 'Deductee details' }} - {{ $quarters[$quarter] }} FY {{ $fiscal_year }}" bodyClass="p-0" class="accounting-dense">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-3">Section</th><th>{{ $isSalary ? 'Employee' : 'Deductee' }}</th><th>PAN</th><th>{{ $isSalary ? 'Salary month' : 'Date' }}</th>
                            <th class="text-end">{{ $isSalary ? 'Salary' : 'Amount' }}</th>
                            @unless ($isSalary)<th class="text-end">Rate</th>@endunless
                            <th class="text-end">TDS</th><th>Challan</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13">
                        @forelse ($deductions as $d)
                            <tr>
                                <td class="ps-3">{{ $d->section?->code }}</td>
                                <td>{{ $d->party_name }}</td>
                                <td>{{ $d->pan ?: 'PANNOTAVBL' }}</td>
                                <td>{{ $isSalary ? $d->deduction_date->format('M Y') : $d->deduction_date->format('d M Y') }}</td>
                                <td class="text-end">{{ format_currency($d->base_amount + $d->catchup_base) }}</td>
                                @unless ($isSalary)<td class="text-end">{{ rtrim(rtrim(number_format($d->rate, 3), '0'), '.') }}%</td>@endunless
                                <td class="text-end">{{ format_currency($d->tds_amount) }}</td>
                                <td>{!! $d->challan?->challan_number ?? '<span class="text-danger">not deposited</span>' !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-4 text-muted">
                                {{ $isSalary ? 'No TDS deducted from salaries in this quarter.' : 'No TDS deducted in this quarter.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
        <div class="col-lg-4">
            @if ($isSalary)
                <x-ui.card title="Form 16 - FY {{ $fiscal_year }}" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr><th class="ps-3">Employee</th><th class="text-end">Salary</th><th class="text-end">TDS</th><th class="pe-3"></th></tr>
                        </thead>
                        <tbody class="fs-13">
                            @forelse ($deductees as $row)
                                <tr>
                                    <td class="ps-3">{{ $row->party_name }}<div class="small text-muted">{{ $row->pan ?: 'No PAN' }}</div></td>
                                    <td class="text-end">{{ format_currency((float) $row->salary) }}</td>
                                    <td class="text-end">{{ format_currency((float) $row->tds_amount) }}</td>
                                    <td class="text-end pe-3">
                                        {{-- Also with nil TDS: employees ask for it for loans and visas. --}}
                                        @if ($canExport)
                                            <a href="{{ route('accounting.tds.form16', ['employee' => $row->party_id, 'fy' => $fiscal_year]) }}" class="btn btn-sm btn-light border" title="Download Form 16"><i class="feather-download"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-3 text-muted">No salary in this year's register yet.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
                <p class="small text-muted mt-2">Salary TDS comes from posted payroll runs. Runs posted before the register existed can be brought in with <code>php artisan tds:sync-payroll</code>. Issue the TRACES Part A with Part B after the year's 24Q is filed.</p>
            @else
                <x-ui.card title="Form 16A" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table hoverable>
                        <tbody class="fs-13">
                            @forelse ($deductees as $row)
                                <tr>
                                    <td class="ps-3">{{ $row->party_name }}<div class="small text-muted">{{ $row->pan ?: 'No PAN' }}</div></td>
                                    <td class="text-end">{{ format_currency((float) $row->tds_amount) }}</td>
                                    <td class="text-end pe-3">
                                        @if ($canExport)
                                            <a href="{{ route('accounting.tds.form16a', ['vendor' => $row->party_id, 'fy' => $fiscal_year, 'quarter' => $quarter]) }}" class="btn btn-sm btn-light border" title="Download Form 16A"><i class="feather-download"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-center py-3 text-muted">No deductees this quarter.</td></tr>
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
                <p class="small text-muted mt-2">The official Form 16A must be downloaded from TRACES once the 26Q is filed; this one is for your records and the vendor's reconciliation.</p>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; }
    </style>
@endpush
