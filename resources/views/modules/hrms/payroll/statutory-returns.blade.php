@extends('layouts.duralux')

@section('title', 'PF & ESI Returns | SaaS ERP')
@section('page-title', 'PF & ESI Returns')
@section('breadcrumb', 'HRMS / Payroll / PF & ESI Returns')

{{-- Same filter bar as the TDS screens. --}}
@include('modules.accounting.tds._filter-styles')

@section('content')
    <x-ui.card bodyClass="p-3" class="mb-3">
        <form method="GET" class="tds-filters">
            <div class="tds-filter">
                <label for="psMonth">Wage month</label>
                <select name="month" id="psMonth" class="form-select" data-no-search>
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}" @selected($value === $month)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="primary">Show</x-ui.button>
            <x-ui.button href="{{ route('hrms.payroll.statutory-returns.index') }}" variant="light" class="border" icon="feather-rotate-ccw">Reset</x-ui.button>
            @if ($canExport)
                <div class="tds-filter-actions">
                    <x-ui.button href="{{ route('hrms.payroll.statutory-returns.ecr', ['month' => $month]) }}" variant="light" class="border" icon="feather-download">PF ECR (.txt)</x-ui.button>
                    <x-ui.button href="{{ route('hrms.payroll.statutory-returns.esi', ['month' => $month]) }}" variant="light" class="border" icon="feather-download">ESIC sheet (.xlsx)</x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.card>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="p-3 border rounded h-100">
                <div class="fs-11 text-uppercase text-muted fw-bold mb-2">PF - {{ $pfTotals['members'] }} member(s)</div>
                <div class="d-flex flex-wrap gap-4 fs-13">
                    <div><div class="text-muted">EPF wages</div><div class="fw-bold">{{ format_currency($pfTotals['epf_wages']) }}</div></div>
                    <div><div class="text-muted">Employee 12%</div><div class="fw-bold">{{ format_currency($pfTotals['epf_employee']) }}</div></div>
                    <div><div class="text-muted">EPS 8.33%</div><div class="fw-bold">{{ format_currency($pfTotals['eps_employer']) }}</div></div>
                    <div><div class="text-muted">EPF 3.67%</div><div class="fw-bold">{{ format_currency($pfTotals['epf_employer_diff']) }}</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 border rounded h-100">
                <div class="fs-11 text-uppercase text-muted fw-bold mb-2">ESI - {{ $esiTotals['members'] }} member(s)</div>
                <div class="d-flex flex-wrap gap-4 fs-13">
                    <div><div class="text-muted">ESI wages</div><div class="fw-bold">{{ format_currency($esiTotals['esi_wages']) }}</div></div>
                    <div><div class="text-muted">Employee 0.75%</div><div class="fw-bold">{{ format_currency($esiTotals['esi_employee']) }}</div></div>
                    <div><div class="text-muted">Employer 3.25%</div><div class="fw-bold">{{ format_currency($esiTotals['esi_employer']) }}</div></div>
                </div>
            </div>
        </div>
    </div>

    @if ($issues)
        <div class="alert alert-warning">
            <strong>Fix before filing:</strong> (add the numbers on the employee's profile, then they appear in the files)
            <ul class="mb-0">@foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
        </div>
    @endif

    <x-ui.card title="Employees - {{ $months[$month] ?? $month }}" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-3">Employee</th><th>UAN</th><th>ESI IP</th>
                    <th class="text-end">Gross</th><th class="text-end">Paid / NCP days</th>
                    <th class="text-end">EPF wages</th><th class="text-end">EPS wages</th><th class="text-end">PF 12%</th><th class="text-end">EPS</th><th class="text-end">EPF diff</th>
                    <th class="text-end">ESI wages</th><th class="text-end">ESI 0.75%</th><th class="text-end pe-3">ESI 3.25%</th>
                </tr>
            </thead>
            <tbody class="fs-13">
                @forelse ($lines as $line)
                    <tr>
                        <td class="ps-3">{{ $line->employee_name }}</td>
                        <td>{!! $line->uan_number ?: ($line->hasPf() ? '<span class="text-danger">missing</span>' : '—') !!}</td>
                        <td>{!! $line->esi_ip_number ?: ($line->hasEsi() ? '<span class="text-danger">missing</span>' : '—') !!}</td>
                        <td class="text-end">{{ format_currency($line->gross_wages) }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($line->paid_days, 1), '0'), '.') }} / {{ rtrim(rtrim(number_format($line->ncp_days, 1), '0'), '.') }}</td>
                        <td class="text-end">{{ format_currency($line->epf_wages) }}</td>
                        <td class="text-end">{{ format_currency($line->eps_wages) }}</td>
                        <td class="text-end">{{ format_currency($line->epf_employee) }}</td>
                        <td class="text-end">{{ format_currency($line->eps_employer) }}</td>
                        <td class="text-end">{{ format_currency($line->epf_employer_diff) }}</td>
                        <td class="text-end">{{ format_currency($line->esi_wages) }}</td>
                        <td class="text-end">{{ format_currency($line->esi_employee) }}</td>
                        <td class="text-end pe-3">{{ format_currency($line->esi_employer) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="text-center py-4 text-muted">
                        No posted payroll for this month. Runs posted before this screen existed can be added with <code>php artisan payroll:sync-statutory</code>.
                    </td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
    <p class="small text-muted mt-2">The ECR is EPFO's ECR 2.0 text format (upload under Payments &gt; ECR Upload). The ESIC sheet follows the portal's monthly contribution template; fill the reason code for anyone with zero days before uploading.</p>
@endsection

@push('styles')
    <style>
        .accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; white-space: nowrap; }
    </style>
@endpush
