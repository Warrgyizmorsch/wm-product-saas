@extends('layouts.duralux')

@section('title', 'Deviations & Waivers Log | SaaS ERP')
@section('page-title', 'Deviations & Waivers Register')
@section('breadcrumb', 'Deviations')

@section('content')
    <div class="row g-4">
        {{-- Log Deviation Request Form --}}
        <div class="col-md-4">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4">Request Deviation / Waiver</h5>
                <form method="POST" action="{{ route('production.deviations.store') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Deviation Type</label>
                        <select name="type" class="form-select" required>
                            <option value="temporary">Temporary Deviation (Time-bound)</option>
                            <option value="permanent">Permanent Deviation</option>
                            <option value="customer_waiver">Customer Quality Waiver</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Justification & Scope Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Explain waiver justification..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Expiration Date (Optional)</label>
                        <input type="date" name="expiration_date" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Maximum Quantity allowed (Optional)</label>
                        <input type="number" step="1" name="expiration_quantity" class="form-control" placeholder="Qty">
                    </div>

                    <button type="submit" class="btn btn-warning w-100">Submit Waiver Request</button>
                </form>
            </div>
        </div>

        {{-- Deviations log list --}}
        <div class="col-md-8">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4">Deviation & Quality Waivers Log</h5>
                
                @if (session('success'))
                    <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Deviation Number</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Expiration</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($deviations as $dev)
                                <tr>
                                    <td class="font-monospace fw-bold text-warning">{{ $dev->deviation_number }}</td>
                                    <td class="text-capitalize">{{ str_replace('_', ' ', $dev->type) }}</td>
                                    <td>
                                        @php
                                            $stClass = match($dev->status) {
                                                'draft' => 'bg-soft-secondary text-secondary',
                                                'submitted' => 'bg-soft-warning text-warning',
                                                'approved' => 'bg-soft-success text-success',
                                                default => 'bg-soft-danger text-danger',
                                            };
                                        @endphp
                                        <span class="badge {{ $stClass }}">{{ strtoupper($dev->status) }}</span>
                                    </td>
                                    <td>
                                        @if($dev->expiration_date)
                                            Date: {{ $dev->expiration_date->toDateString() }}
                                        @elseif($dev->expiration_quantity)
                                            Qty limit: {{ $dev->expiration_quantity }}
                                        @else
                                            Indefinite
                                        @endif
                                    </td>
                                    <td>
                                        @if($dev->status === 'draft' || $dev->status === 'submitted')
                                            <form method="POST" action="{{ route('production.quality.deviations.approve', $dev->id) }}" class="d-flex align-items-center gap-1">
                                                @csrf
                                                <input type="text" name="esignature" class="form-control form-control-sm" placeholder="Sign PIN" style="width: 80px;" required>
                                                <button type="submit" class="btn btn-xs btn-success">Approve</button>
                                            </form>
                                        @else
                                            <span class="text-success"><i class="feather-check-circle me-1"></i>Approved</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No deviations recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
