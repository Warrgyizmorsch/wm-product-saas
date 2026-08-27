@extends('layouts.duralux')

@section('title', 'Subcontract Delivery Challans | SaaS ERP')
@section('page-title', 'Subcontract Delivery Challans (Gate Passes)')
@section('breadcrumb', 'Delivery Challans')

@section('content')
<div class="erp-single-panel bg-white">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Top Header & Search Bar -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div>
                <h4 class="fw-bold text-dark mb-1"><i class="feather-truck me-2 text-primary"></i>Subcontract Delivery Challans (Gate Passes)</h4>
                <p class="text-muted fs-12 mb-0">Outward company material gate pass management and vendor dispatch tracking.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('production.subcontract.delivery-challans.create') }}" class="btn btn-sm btn-primary px-3 fw-bold">
                    <i class="feather-plus me-1"></i> New Delivery Challan
                </a>
            </div>
        </div>

        {{-- Summary Metrics Header --}}
        <div class="row g-3 mb-4">
            <div class="col">
                <div class="border rounded p-3 text-center bg-white">
                    <span class="fs-11 text-uppercase text-muted fw-bold">Total Challans</span>
                    <h4 class="fw-bold text-dark mb-0 mt-1 font-monospace">{{ number_format($statusCounts['total']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 text-center bg-soft-warning">
                    <span class="fs-11 text-uppercase text-warning fw-bold">Draft Gate Passes</span>
                    <h4 class="fw-bold text-warning-emphasis mb-0 mt-1 font-monospace">{{ number_format($statusCounts['draft']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 text-center bg-soft-success">
                    <span class="fs-11 text-uppercase text-success fw-bold">Dispatched (Stock Out)</span>
                    <h4 class="fw-bold text-success mb-0 mt-1 font-monospace">{{ number_format($statusCounts['dispatched']) }}</h4>
                </div>
            </div>
            <div class="col">
                <div class="border rounded p-3 text-center bg-soft-info">
                    <span class="fs-11 text-uppercase text-info fw-bold">Completed</span>
                    <h4 class="fw-bold text-info-emphasis mb-0 mt-1 font-monospace">{{ number_format($statusCounts['completed']) }}</h4>
                </div>
            </div>
        </div>

        {{-- Search Filter --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <form action="{{ route('production.subcontract.delivery-challans.index') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Challan # / Vehicle / LR #" value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="feather-search"></i> Search</button>
            </form>
        </div>

        {{-- Challans Register Table --}}
        <div class="mb-4">
            <x-ui.odoo-form-ui type="table">
                <thead class="table-light fs-11 text-uppercase text-muted">
                    <tr>
                        <th>Challan #</th>
                        <th>Date</th>
                        <th>Subcontractor / Vendor</th>
                        <th>Source Warehouse</th>
                        <th>Linked Production Order</th>
                        <th>Vehicle & Transporter</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($challans as $ch)
                        <tr>
                            <td>
                                <a href="{{ route('production.subcontract.delivery-challans.show', $ch->id) }}" class="fw-bold text-primary font-monospace">
                                    {{ $ch->challan_number }}
                                </a>
                            </td>
                            <td>{{ $ch->challan_date->format('d/m/Y') }}</td>
                            <td>
                                <div class="fw-semibold text-dark"><i class="feather-truck me-1 text-primary"></i>{{ $ch->vendor?->name ?? 'Vendor' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fs-11"><i class="feather-home me-1"></i>{{ $ch->warehouse?->name ?? 'Unassigned' }}</span>
                            </td>
                            <td>
                                @if($ch->productionOrder)
                                    <a href="{{ route('production.orders.show', $ch->production_order_id) }}" class="fw-bold text-primary font-monospace fs-12">
                                        {{ $ch->productionOrder->order_number }}
                                    </a>
                                    @if($ch->operation)
                                        <div class="fs-10 text-muted">Op #{{ $ch->operation->operation_number }} — {{ $ch->operation->name }}</div>
                                    @endif
                                @else
                                    <span class="text-muted fs-11">Direct Dispatch</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-monospace fw-bold text-dark">{{ $ch->vehicle_number ?: '—' }}</div>
                                <small class="text-muted fs-11">{{ $ch->transporter_name }}</small>
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge :status="$ch->status" />
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown>
                                    <a class="dropdown-item" href="{{ route('production.subcontract.delivery-challans.show', $ch->id) }}"><i class="feather-eye me-2 text-primary"></i>View Details</a>
                                    <a class="dropdown-item" href="{{ route('production.subcontract.delivery-challans.print', $ch->id) }}" target="_blank"><i class="feather-printer me-2 text-info"></i>Print Gate Pass</a>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="feather-truck fs-24 d-block mb-2 text-muted"></i>
                                No subcontract delivery challans found. <a href="{{ route('production.subcontract.delivery-challans.create') }}" class="text-primary">Create First Gate Pass</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($challans->hasPages())
            <div class="pt-3 border-top">
                {{ $challans->links() }}
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection
