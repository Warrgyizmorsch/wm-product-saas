@extends('layouts.duralux')

@php
    $eligibleForCapitalization = in_array($asset->status, [
        \App\Domains\HRMS\Models\Asset::STATUS_AVAILABLE,
        \App\Domains\HRMS\Models\Asset::STATUS_DRAFT,
        \App\Domains\HRMS\Models\Asset::STATUS_PENDING_CAPITALIZATION,
    ], true);
@endphp

@section('title', $asset->asset_code . ' | SaaS ERP')
@section('page-title', $asset->asset_code . ' — ' . $asset->name)
@section('breadcrumb', 'Accounting / Fixed Assets / ' . $asset->asset_code)

@section('page-actions')
    <x-ui.button href="{{ route('accounting.fixed-assets.index') }}" variant="light" icon="feather-arrow-left" class="border">
        Back to Register
    </x-ui.button>
    @if ($canCapitalize && $eligibleForCapitalization)
        <x-ui.button type="button" variant="primary" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#capitalizeModal">
            Capitalize Asset
        </x-ui.button>
    @endif
@endsection

@section('content')

    <x-ui.card class="mb-4">
        <div class="row g-4 fs-13">
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Asset Code</span>
                <span class="fw-bold text-dark font-monospace">{{ $asset->asset_code }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Category</span>
                <span class="fw-bold text-dark">{{ $asset->category?->name ?? '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Status</span>
                <x-ui.status-badge :status="$asset->status" />
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Assigned To</span>
                <span class="fw-bold text-dark">{{ $asset->assignedEmployee?->full_name ?? '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Purchase Date</span>
                <span class="fw-bold text-dark">{{ $asset->purchase_date?->format('d M Y') ?? '—' }}</span>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card class="mb-4">
        <x-slot:title>Financials</x-slot:title>
        <div class="row g-4 fs-13">
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Capitalization Cost</span>
                <span class="fw-bold text-dark">{{ $asset->capitalization_cost !== null ? number_format($asset->capitalization_cost, 2) : '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Residual Value</span>
                <span class="fw-bold text-dark">{{ $asset->residual_value !== null ? number_format($asset->residual_value, 2) : '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Accum. Depreciation</span>
                <span class="fw-bold text-dark">{{ $asset->capitalization_cost !== null ? number_format($asset->accumulated_depreciation, 2) : '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Book Value</span>
                <span class="fw-bold text-success">{{ $asset->book_value !== null ? number_format($asset->book_value, 2) : '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Useful Life</span>
                <span class="fw-bold text-dark">{{ $asset->useful_life_months ? $asset->useful_life_months . ' months' : '—' }}</span>
            </div>
            <div class="col-md-2">
                <span class="text-muted fs-11 text-uppercase d-block mb-1">Method</span>
                <span class="fw-bold text-dark text-capitalize">{{ $asset->depreciation_method ? str_replace('_', ' ', $asset->depreciation_method) : '—' }}</span>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card bodyClass="p-0" class="mb-4">
        <x-slot:title>Depreciation Schedule</x-slot:title>
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Period</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">Depreciation</th>
                    <th class="text-end">Closing</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($asset->depreciationSchedules as $schedule)
                    <tr>
                        <td class="ps-4">{{ $schedule->period_month }}/{{ $schedule->period_year }}</td>
                        <td class="text-end">{{ number_format($schedule->opening_book_value, 2) }}</td>
                        <td class="text-end">{{ number_format($schedule->depreciation_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($schedule->closing_book_value, 2) }}</td>
                        <td><x-ui.status-badge :status="$schedule->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No depreciation schedules yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    @if ($asset->disposals->isNotEmpty() || $asset->writeOffs->isNotEmpty() || $asset->revaluations->isNotEmpty())
        <x-ui.card bodyClass="p-0" class="mb-4">
            <x-slot:title>Disposal / Write-off / Revaluation History</x-slot:title>
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Type</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @foreach ($asset->disposals as $disposal)
                        <tr>
                            <td class="ps-4">Disposal ({{ ucfirst($disposal->disposal_type) }})</td>
                            <td>{{ $disposal->disposal_date->format('d M Y') }}</td>
                            <td class="text-end">{{ number_format($disposal->gain_loss_amount, 2) }}</td>
                            <td><x-ui.status-badge :status="$disposal->status" /></td>
                        </tr>
                    @endforeach
                    @foreach ($asset->writeOffs as $writeOff)
                        <tr>
                            <td class="ps-4">Write-off</td>
                            <td>{{ $writeOff->write_off_date->format('d M Y') }}</td>
                            <td class="text-end">{{ number_format($writeOff->net_book_value, 2) }}</td>
                            <td><x-ui.status-badge :status="$writeOff->status" /></td>
                        </tr>
                    @endforeach
                    @foreach ($asset->revaluations as $revaluation)
                        <tr>
                            <td class="ps-4">Revaluation</td>
                            <td>{{ $revaluation->revaluation_date->format('d M Y') }}</td>
                            <td class="text-end">{{ number_format($revaluation->revaluation_surplus_deficit, 2) }}</td>
                            <td><x-ui.status-badge :status="$revaluation->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
    @endif

    @if ($canCapitalize && $eligibleForCapitalization)
        <x-ui.modal id="capitalizeModal" title="Capitalize {{ $asset->asset_code }}"
                    :formAction="route('accounting.fixed-assets.capitalize', $asset)" submitText="Capitalize">
            <p class="fs-13 text-muted">Establishes this asset's financial identity: capitalization cost, useful life, depreciation method and start date. Leave a field blank to use the category's default where available.</p>
            <div class="row g-3 fs-13">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Acquisition Cost" name="acquisition_cost" :value="old('acquisition_cost', $asset->purchase_cost)" placeholder="0.00" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Directly Attributable Cost" name="directly_attributable_cost" :value="old('directly_attributable_cost')" placeholder="0.00" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Non-recoverable Tax" name="non_recoverable_tax" :value="old('non_recoverable_tax')" placeholder="0.00" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Residual Value" name="residual_value" :value="old('residual_value')" placeholder="Defaults from category" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Useful Life (months)" name="useful_life_months" :value="old('useful_life_months', $asset->category?->default_useful_life_months)" placeholder="e.g. 36" />
                </div>
                <div class="col-md-6">
                    <x-ui.select label="Depreciation Method" name="depreciation_method" :selected="old('depreciation_method', $asset->category?->default_depreciation_method)" :options="[
                        '' => 'Straight Line (default)',
                        'straight_line' => 'Straight Line',
                        'wdv' => 'Written Down Value (WDV)',
                    ]" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Capitalization Date" name="capitalization_date" :value="old('capitalization_date', now()->toDateString())" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Depreciation Start Date" name="depreciation_start_date" :value="old('depreciation_start_date', now()->toDateString())" />
                </div>
            </div>
        </x-ui.modal>
    @endif
@endsection
