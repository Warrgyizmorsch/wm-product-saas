@extends('layouts.duralux')

@section('title', 'Deviations & Waivers Log | SaaS ERP')
@section('page-title', 'Deviations & Waivers Register')
@section('breadcrumb', 'Deviations')

@section('content')
    {{-- Toast alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="row g-4">
        {{-- Log Deviation Request Form --}}
        <div class="col-md-4">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4"><i class="feather-alert-triangle me-2 text-warning"></i>Request Deviation / Waiver</h5>
                <form method="POST" action="{{ route('production.deviations.store') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Deviation Type</label>
                        <select name="type" class="form-select" required>
                            <option value="temporary">Temporary Deviation (Time-bound)</option>
                            <option value="permanent">Permanent Deviation</option>
                            <option value="customer_waiver">Customer Quality Waiver</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Justification & Scope Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Explain waiver justification..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Expiration Date (Optional)</label>
                        <input type="date" name="expiration_date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Maximum Quantity allowed (Optional)</label>
                        <input type="number" step="1" name="expiration_quantity" class="form-control" placeholder="Quantity Limit">
                    </div>

                    <button type="submit" class="btn btn-warning w-100 mt-2">Submit Waiver Request</button>
                </form>
            </div>
        </div>

        {{-- Deviations log list --}}
        <div class="col-md-8">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4"><i class="feather-list me-2 text-primary"></i>Deviation & Quality Waivers Log</h5>
                
                <!-- Deviation Table -->
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 25%">Deviation Number</th>
                            <th style="width: 20%">Type</th>
                            <th style="width: 15%">Status</th>
                            <th style="width: 20%">Expiration</th>
                            <th class="text-end" style="width: 20%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deviations as $dev)
                            <tr>
                                <td class="font-monospace fw-bold text-warning">{{ $dev->deviation_number }}</td>
                                <td class="text-capitalize text-dark fw-medium">{{ str_replace('_', ' ', $dev->type) }}</td>
                                <td>
                                    @if($dev->status === 'approved')
                                        <span class="erp-badge-active">Approved</span>
                                    @elseif($dev->status === 'submitted')
                                        <span class="erp-badge-pending">Submitted</span>
                                    @else
                                        <span class="erp-badge-draft text-uppercase">{{ $dev->status }}</span>
                                    @endif
                                </td>
                                <td class="text-muted">
                                    @if($dev->expiration_date)
                                        Date: {{ $dev->expiration_date->format('d/m/Y') }}
                                    @elseif($dev->expiration_quantity)
                                        Limit: {{ number_format($dev->expiration_quantity) }} pcs
                                    @else
                                        Indefinite
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($dev->status === 'draft' || $dev->status === 'submitted')
                                        <form method="POST" action="{{ route('production.quality.deviations.approve', $dev->id) }}" class="d-flex align-items-center justify-content-end gap-1">
                                            @csrf
                                            <input type="text" name="esignature" class="form-control form-control-sm" placeholder="Sign PIN" style="width: 80px;" required>
                                            <button type="submit" class="btn btn-xs btn-success py-1">Approve</button>
                                        </form>
                                    @else
                                        <span class="text-success fs-11 fw-semibold"><i class="feather-check-circle me-1"></i>Approved</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="feather-info me-2 fs-16"></i>No deviations recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>

                <div class="mt-4">
                    {{ $deviations->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
