@extends('layouts.duralux')

@section('title', 'Fiscal Years & Periods | SaaS ERP')
@section('page-title', 'Fiscal Years & Periods')
@section('breadcrumb', 'Accounting / Fiscal Years')

@section('page-actions')
    @can('create', \App\Domains\Accounting\Models\FiscalYear::class)
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#newFiscalYearModal">
            New Fiscal Year
        </x-ui.button>
    @endcan
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            <ul class="fs-12 mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @forelse ($fiscalYears as $fiscalYear)
        <x-ui.card class="mb-4 accounting-dense">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-calendar me-2 text-primary"></i>{{ $fiscalYear->name }}
                    <span class="fs-12 text-muted fw-normal ms-2">
                        {{ $fiscalYear->start_date->format('d M Y') }} &ndash; {{ $fiscalYear->end_date->format('d M Y') }}
                    </span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    @if ($fiscalYear->isOpen())
                        <x-ui.badge variant="success" soft>Open</x-ui.badge>
                        @can('close', $fiscalYear)
                            <form action="{{ route('accounting.fiscal-years.close', $fiscalYear) }}" method="POST" class="d-inline">
                                @csrf
                                <x-ui.button type="button" variant="danger" size="sm"
                                        data-confirm-title="Close Fiscal Year"
                                        data-confirm-message="Close {{ $fiscalYear->name }}? This cannot be undone.">Close Fiscal Year</x-ui.button>
                            </form>
                        @endcan
                    @else
                        <x-ui.badge variant="secondary" soft>Closed</x-ui.badge>
                    @endif
                </div>
            </div>

            <div class="mt-3">
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Period</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @foreach ($fiscalYear->periods as $period)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $period->name }}</td>
                                <td>{{ $period->start_date->format('d M Y') }} &ndash; {{ $period->end_date->format('d M Y') }}</td>
                                <td>
                                    @if ($period->status === 'open')
                                        <x-ui.badge variant="success" soft>Open</x-ui.badge>
                                    @elseif ($period->status === 'closed')
                                        <x-ui.badge variant="warning" soft>Closed</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="secondary" soft>Locked</x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    @can('manage', $period)
                                        @if ($period->status === 'open')
                                            <form action="{{ route('accounting.periods.close', $period) }}" method="POST" class="d-inline">
                                                @csrf
                                                <x-ui.button type="button" variant="warning" size="sm"
                                                        data-confirm-title="Close Period"
                                                        data-confirm-message="Close {{ $period->name }}? No new journals may post to it afterwards.">Close</x-ui.button>
                                            </form>
                                        @endif
                                        @if ($period->status === 'closed')
                                            <form action="{{ route('accounting.periods.lock', $period) }}" method="POST" class="d-inline ms-1">
                                                @csrf
                                                <x-ui.button type="button" variant="secondary" size="sm"
                                                        data-confirm-title="Lock Period"
                                                        data-confirm-message="Lock {{ $period->name }}? Locked periods cannot be reopened from here.">Lock</x-ui.button>
                                            </form>
                                            <form action="{{ route('accounting.periods.reopen', $period) }}" method="POST" class="d-inline ms-1">
                                                @csrf
                                                <x-ui.button type="submit" variant="success" size="sm">Reopen</x-ui.button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        </x-ui.card>
    @empty
        <x-ui.card>
            <div class="text-center py-5 text-muted">
                <i class="feather-calendar fs-1 mb-2 d-block"></i>
                No fiscal years set up yet.
            </div>
        </x-ui.card>
    @endforelse

    <x-ui.modal id="newFiscalYearModal" title="New Fiscal Year" :formAction="route('accounting.fiscal-years.store')" submitText="Create">
        <x-ui.input label="Name" name="name" placeholder="e.g. FY 2026-27" required="true" />
        <x-ui.input label="Start Date" name="start_date" type="date" required="true" />
        <x-ui.input label="End Date" name="end_date" type="date" required="true" />
        <p class="fs-11 text-muted mb-0">Monthly accounting periods will be generated automatically for this date range.</p>
    </x-ui.modal>

    <x-ui.confirm-modal />
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
