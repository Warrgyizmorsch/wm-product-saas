<div class="row g-4 mt-2">
    {{-- Serials List --}}
    <div class="col-lg-6">
        <div class="card border border-light shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="fw-bold text-dark mb-0">Registered Serials</h6>
            </div>
            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-11 text-muted uppercase font-semibold">
                            <tr>
                                <th>Serial Number</th>
                                <th>Status</th>
                                <th>Scanned Tag</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($serials as $serial)
                                <tr>
                                    <td class="fw-bold text-dark font-monospace">{{ $serial->serial_number }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match($serial->status) {
                                                'produced' => 'bg-soft-success text-success',
                                                'scrapped' => 'bg-soft-danger text-danger',
                                                'reworked' => 'bg-soft-warning text-warning',
                                                default => 'bg-soft-primary text-primary',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ strtoupper($serial->status) }}</span>
                                    </td>
                                    <td class="text-muted font-monospace fs-11">
                                        {{ $serial->barcode ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        No serial numbers registered yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="col-lg-6">
        {{-- Range Generation --}}
        <div class="card border border-light shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="fw-bold text-dark mb-0">Generate Serial Numbers Range</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('production.mes.serials.generate') }}">
                    @csrf
                    <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                    <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Prefix</label>
                            <input type="text" name="prefix" class="form-control" value="SN-{{ date('Y') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="{{ max(1, $order->quantity_ordered - $serials->count()) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Starting Index</label>
                            <input type="number" name="start_num" class="form-control" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Select Batch (Optional)</label>
                            <select name="batch_id" class="form-select">
                                <option value="">None (Independent Serial)</option>
                                @foreach($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->batch_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-touch btn-primary w-100">
                                <i class="feather-plus-circle me-1"></i> Generate Range
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Single Manual Registration --}}
        <div class="card border border-light shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="fw-bold text-dark mb-0">Register Single Serial Number</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('production.mes.serials.manual-assign') }}">
                    @csrf
                    <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                    <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Serial Number</label>
                            <input type="text" name="serial_number" class="form-control" placeholder="Enter custom serial..." required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Select Batch (Optional)</label>
                            <select name="batch_id" class="form-select">
                                <option value="">None (Independent Serial)</option>
                                @foreach($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->batch_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-touch btn-light border w-100">
                                <i class="feather-tag me-1"></i> Register Custom Serial
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
