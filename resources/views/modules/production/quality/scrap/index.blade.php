@extends('layouts.duralux')

@section('title', 'Scrap Disposals Log | SaaS ERP')
@section('page-title', 'Scrap & Waste Disposal Register')
@section('breadcrumb', 'Scrap')

@section('content')
    {{-- Toast alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="row g-4">
        {{-- Log Scrap Form --}}
        <div class="col-md-4">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4"><i class="feather-trash-2 me-2 text-danger"></i>Log Waste Scrap</h5>
                <form method="POST" action="{{ route('production.scrap.store') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Waste Category</label>
                        <select name="category" class="form-select" required>
                            <option value="raw_material">Raw Material Scrap</option>
                            <option value="finished_good">Finished Defective Goods</option>
                            <option value="scrap_metal">Metal / Swarf Scrap</option>
                            <option value="chemical">Chemical / Fluid Waste</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reason Code</label>
                        <select name="reason_code" class="form-select" required>
                            <option value="defect">Quality Defect Failure</option>
                            <option value="damage">Physical Handling Damage</option>
                            <option value="excess">Excess Leftover Swarf</option>
                            <option value="obsolete">Obsolete Material Expiry</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Quantity</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" placeholder="Quantity" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Estimated Cost Value ({{ active_currency_symbol() }})</label>
                        <input type="number" step="0.01" name="cost" class="form-control" placeholder="Cost Value" required>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 mt-2">Record Waste Scrap</button>
                </form>
            </div>
        </div>

        {{-- Scrap log list --}}
        <div class="col-md-8">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4"><i class="feather-list me-2 text-primary"></i>Scrap Logs & Disposals Queue</h5>

                <!-- Scrap Table -->
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 8%">ID</th>
                            <th style="width: 15%">Category</th>
                            <th style="width: 22%">Production Order &amp; Product</th>
                            <th style="width: 15%">Reason</th>
                            <th style="width: 12%" class="text-end">Qty</th>
                            <th style="width: 13%" class="text-end">Value</th>
                            <th style="width: 10%">Status</th>
                            <th class="text-end" style="width: 5%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scraps as $sc)
                            <tr>
                                <td class="font-monospace fw-bold text-dark">#{{ $sc->id }}</td>
                                <td class="text-capitalize text-dark fw-medium">{{ str_replace('_', ' ', $sc->category) }}</td>
                                <td>
                                    @if($sc->ncr && $sc->ncr->order)
                                        <div class="d-flex flex-column">
                                            <a href="{{ route('production.orders.show', $sc->ncr->order->id) }}" class="fw-bold text-primary">
                                                {{ $sc->ncr->order->order_number }}
                                            </a>
                                            @if($sc->ncr->order->product)
                                                <span class="text-muted fs-11 text-truncate" style="max-width: 180px;">
                                                    {{ $sc->ncr->order->product->name }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-capitalize text-muted">{{ str_replace('_', ' ', $sc->reason_code) }}</td>
                                <td class="text-end fw-bold text-dark">{{ number_format($sc->quantity, 2) }}</td>
                                <td class="text-end text-danger fw-bold">{{ format_currency($sc->cost) }}</td>

                                <td>
                                    @if($sc->status === 'approved')
                                        <span class="erp-badge-active">Approved</span>
                                    @elseif($sc->status === 'pending_approval')
                                        <span class="erp-badge-pending">Pending</span>
                                    @else
                                        <span class="erp-badge-draft text-uppercase">{{ $sc->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($sc->status === 'pending_approval')
                                        <form method="POST" action="{{ route('production.quality.scrap.approve', $sc->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success py-1">Approve</button>
                                        </form>
                                    @else
                                        <span class="text-success fs-11 fw-semibold"><i class="feather-check-circle me-1"></i>Disposed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2 fs-16"></i>No scrap logs registered.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>

                <div class="mt-4">
                    {{ $scraps->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
