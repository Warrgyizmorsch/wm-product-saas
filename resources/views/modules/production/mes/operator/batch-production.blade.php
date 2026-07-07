<div class="row g-4 mt-2">
    {{-- Active Batches List --}}
    <div class="col-lg-6">
        <div class="card border border-light shadow-sm">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="fw-bold text-dark mb-0">Active Batches</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-11 text-muted uppercase font-semibold">
                            <tr>
                                <th>Batch Number</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Actual Qty</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($batches as $batch)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark font-monospace">{{ $batch->batch_number }}</div>
                                        @if($batch->barcode)
                                            <div class="fs-10 text-muted"><i class="feather-tag"></i> {{ $batch->barcode }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-dark">{{ number_format($batch->planned_quantity, 2) }}</td>
                                    <td class="text-end text-success fw-semibold">{{ number_format($batch->actual_quantity, 2) }}</td>
                                    <td class="text-center">
                                        @php
                                            $badgeClass = match($batch->status) {
                                                'completed' => 'bg-soft-success text-success',
                                                'consumed' => 'bg-soft-secondary text-secondary',
                                                'blocked' => 'bg-soft-danger text-danger',
                                                'quarantine' => 'bg-soft-warning text-warning',
                                                default => 'bg-soft-primary text-primary',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ strtoupper($batch->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-light border" onclick="openSplitModal({{ $batch->id }}, '{{ $batch->batch_number }}', {{ $batch->planned_quantity }})">
                                                Split
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        No batches registered for this order yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Create & Merge Operations --}}
    <div class="col-lg-6">
        {{-- Create New Batch --}}
        <div class="card border border-light shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-3">
                <h6 class="fw-bold text-dark mb-0">Create New Batch</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('production.mes.batches.create') }}">
                    @csrf
                    <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                    <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Planned Quantity</label>
                            <input type="number" step="0.0001" name="planned_quantity" class="form-control" value="{{ $order->quantity_ordered }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-touch btn-primary w-100">
                                <i class="feather-plus me-1"></i> Generate New Batch
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Merge Batches --}}
        @if($batches->count() >= 2)
            <div class="card border border-light shadow-sm">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold text-dark mb-0">Merge Batches</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('production.mes.batches.merge') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fs-11 text-muted uppercase font-semibold">Select Batches to Merge</label>
                            <select name="parent_batch_ids[]" class="form-select" multiple style="min-height: 100px;" required>
                                @foreach($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->batch_number }} (Qty: {{ number_format($b->planned_quantity, 2) }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">Hold Ctrl/Cmd to select multiple.</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fs-11 text-muted uppercase font-semibold">Target Quantity</label>
                                <input type="number" step="0.0001" name="target_planned_quantity" class="form-control" placeholder="Merged total qty..." required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-11 text-muted uppercase font-semibold">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Comments...">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-touch btn-warning w-100">
                                    <i class="feather-git-merge me-1"></i> Merge Selected Batches
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Split Batch Modal --}}
<div class="modal fade" id="splitBatchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('production.mes.batches.split') }}">
                @csrf
                <input type="hidden" name="parent_batch_id" id="splitParentId">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Split Batch: <span id="splitBatchName" class="font-monospace"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-muted">
                        Total Quantity to distribute: <strong id="splitTotalLabel"></strong>
                    </div>

                    <div id="splitsContainer">
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-5">
                                <label class="form-label fs-11 uppercase font-semibold mb-1">Child Qty 1</label>
                                <input type="number" step="0.0001" name="splits[0][planned_quantity]" class="form-control" placeholder="Qty..." required>
                            </div>
                            <div class="col-7">
                                <label class="form-label fs-11 uppercase font-semibold mb-1">Remarks</label>
                                <input type="text" name="splits[0][remarks]" class="form-control" placeholder="Optional comments...">
                            </div>
                        </div>
                        <div class="row g-2 mb-2 align-items-end">
                            <div class="col-5">
                                <label class="form-label fs-11 uppercase font-semibold mb-1">Child Qty 2</label>
                                <input type="number" step="0.0001" name="splits[1][planned_quantity]" class="form-control" placeholder="Qty..." required>
                            </div>
                            <div class="col-7">
                                <label class="form-label fs-11 uppercase font-semibold mb-1">Remarks</label>
                                <input type="text" name="splits[1][remarks]" class="form-control" placeholder="Optional comments...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Apply Split</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openSplitModal(id, number, qty) {
        document.getElementById('splitParentId').value = id;
        document.getElementById('splitBatchName').innerText = number;
        document.getElementById('splitTotalLabel').innerText = qty;
        
        let modal = new bootstrap.Modal(document.getElementById('splitBatchModal'));
        modal.show();
    }
</script>
