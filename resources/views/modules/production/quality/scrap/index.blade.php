@extends('layouts.duralux')

@section('title', 'Scrap Disposals Log | SaaS ERP')
@section('page-title', 'Scrap & Waste Disposal Register')
@section('breadcrumb', 'Scrap')

@section('content')
    <div class="row g-4">
        {{-- Log Scrap Form --}}
        <div class="col-md-4">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4">Log Raw Material / Finished Waste Scrap</h5>
                <form method="POST" action="{{ route('production.scrap.store') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Waste Category</label>
                        <select name="category" class="form-select" required>
                            <option value="raw_material">Raw Material Scrap</option>
                            <option value="finished_good">Finished Defective Goods</option>
                            <option value="scrap_metal">Metal / Swarf Scrap</option>
                            <option value="chemical">Chemical / Fluid Waste</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason Code</label>
                        <select name="reason_code" class="form-select" required>
                            <option value="defect">Quality Defect Failure</option>
                            <option value="damage">Physical Handling Damage</option>
                            <option value="excess">Excess Leftover Swarf</option>
                            <option value="obsolete">Obsolete Material Expiry</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Quantity</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" placeholder="Qty" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Estimated Cost Value ($)</label>
                        <input type="number" step="0.01" name="cost" class="form-control" placeholder="Cost" required>
                    </div>

                    <button type="submit" class="btn btn-danger w-100">Record Waste Scrap</button>
                </form>
            </div>
        </div>

        {{-- Scrap log list --}}
        <div class="col-md-8">
            <div class="card border border-light shadow-sm bg-white p-4 rounded">
                <h5 class="fw-bold text-dark mb-4">Scrap Logs & Disposals Queue</h5>
                
                @if (session('success'))
                    <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Reason</th>
                                <th>Qty</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scraps as $sc)
                                <tr>
                                    <td>#{{ $sc->id }}</td>
                                    <td class="text-capitalize">{{ str_replace('_', ' ', $sc->category) }}</td>
                                    <td class="text-capitalize">{{ $sc->reason_code }}</td>
                                    <td class="fw-bold">{{ $sc->quantity }}</td>
                                    <td class="text-danger">${{ number_format($sc->cost, 2) }}</td>
                                    <td>
                                        @php
                                            $stClass = match($sc->status) {
                                                'pending_approval' => 'bg-soft-warning text-warning',
                                                'approved' => 'bg-soft-success text-success',
                                                default => 'bg-soft-secondary text-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $stClass }}">{{ strtoupper($sc->status) }}</span>
                                    </td>
                                    <td>
                                        @if($sc->status === 'pending_approval')
                                            <form method="POST" action="{{ route('production.quality.scrap.approve', $sc->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-success">Approve</button>
                                            </form>
                                        @else
                                            <span class="text-success"><i class="feather-check me-1"></i>Disposed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No scrap logs registered.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
