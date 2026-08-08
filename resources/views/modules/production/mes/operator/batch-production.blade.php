{{-- Operation-Wise Routing Header Card --}}
<x-ui.card class="mb-4 bg-soft-light border">
    <div class="row align-items-center g-3">
        <div class="col-md-3 border-end">
            <span class="text-muted fs-11 uppercase font-semibold d-block">Current Operation</span>
            <strong class="text-dark fs-14">{{ $op->operation_number }} — {{ $op->name }}</strong>
        </div>
        <div class="col-md-3 border-end">
            <span class="text-muted fs-11 uppercase font-semibold d-block">Routing Position</span>
            <x-ui.badge variant="info" soft class="fs-12 font-monospace">
                {{ $batchQueue['meta']['is_first_op'] ? 'Initial Operation (Sequence ' . $op->sequence . ')' : 'Successor Operation (Sequence ' . $op->sequence . ')' }}
            </x-ui.badge>
        </div>
        <div class="col-md-3 border-end">
            <span class="text-muted fs-11 uppercase font-semibold d-block">Transfer / Overlap Rules</span>
            <span class="text-dark fs-13 font-monospace">
                {{ $op->routingOperation?->transfer_batch_quantity > 0 ? 'Batch Transfer (' . number_format($op->routingOperation->transfer_batch_quantity, 2) . ' units)' : 'Standard Transfer' }}
            </span>
        </div>
        <div class="col-md-3">
            <span class="text-muted fs-11 uppercase font-semibold d-block">Next Destination</span>
            @if($batchQueue['meta']['next_op'])
                <strong class="text-primary font-monospace fs-13">
                    OP{{ $batchQueue['meta']['next_op']->sequence }} — {{ $batchQueue['meta']['next_op']->name }}
                </strong>
            @else
                <x-ui.badge variant="success" soft class="fs-11">Final Operation (Finished Goods)</x-ui.badge>
            @endif
        </div>
    </div>
</x-ui.card>

<div class="row g-4 mt-1">
    {{-- Active & Operation Queue Column --}}
    <div class="col-lg-8">

        {{-- Section 1: Active / Ready to Process Batches --}}
        <x-ui.card :title="__('production.active_batches') . ' at ' . $op->name" class="mb-4">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 22%">Batch Number</th>
                        @if($batchQueue['meta']['is_first_op'])
                            <th style="width: 12%" class="text-end">Planned Qty</th>
                        @else
                            <th style="width: 12%" class="text-end">Transferred In</th>
                        @endif
                        <th style="width: 12%" class="text-end">Processed</th>
                        <th style="width: 12%" class="text-end">Remaining</th>
                        <th style="width: 12%" class="text-end">Transferred Out</th>
                        <th style="width: 12%" class="text-center">Status</th>
                        <th style="width: 18%" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batchQueue['active'] as $item)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark font-monospace">{{ $item['batch']->batch_number }}</div>
                                @if(!$batchQueue['meta']['is_first_op'] && $item['previous_operation'])
                                    <div class="fs-10 text-muted"><i class="feather-corner-down-right me-1"></i>From: {{ $item['previous_operation']->name }}</div>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                {{ number_format($batchQueue['meta']['is_first_op'] ? $item['planned_quantity'] : $item['input_received'], 2) }}
                            </td>
                            <td class="text-end fw-semibold text-dark">{{ number_format($item['processed_at_operation'], 2) }}</td>
                            <td class="text-end text-danger fw-semibold">{{ number_format($item['remaining_to_process'], 2) }}</td>
                            <td class="text-end text-muted">{{ number_format($item['transferred_to_next'], 2) }}</td>
                            <td class="text-center">
                                @php
                                    $badgeVariant = match ($item['display_status']) {
                                        'READY' => 'success',
                                        'PARTIALLY_PROCESSED' => 'primary',
                                        'WAITING_FOR_TRANSFER' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <x-ui.badge :variant="$badgeVariant" soft class="fs-11">
                                    {{ str_replace('_', ' ', $item['display_status']) }}
                                </x-ui.badge>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    @if($item['can_log_progress'])
                                        <x-ui.button size="sm" variant="info" class="text-white py-1 px-2"
                                            onclick="openLogProgressForBatch({{ $item['batch']->id }}, '{{ $item['batch']->batch_number }}', {{ $item['remaining_to_process'] }})"
                                            title="Log Progress">
                                            <i class="feather-edit-3 me-1"></i> Log
                                        </x-ui.button>
                                    @endif

                                    @if($item['can_transfer'])
                                        @php
                                            $wipId = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $op->tenant_id)
                                                ->where('production_order_id', $op->production_order_id)
                                                ->where('production_batch_id', $item['batch']->id)
                                                ->value('id');
                                        @endphp
                                        @if($wipId && $item['next_operation'])
                                            <x-ui.button size="sm" variant="success" class="py-1 px-2"
                                                onclick="openTransferModal({{ $wipId }}, '{{ $item['batch']->batch_number }}', {{ $op->routing_operation_id }}, {{ $item['next_operation']->routing_operation_id }}, '{{ $item['next_operation']->name }}', {{ $item['ready_to_transfer'] }})"
                                                title="Transfer WIP">
                                                <i class="feather-send me-1"></i> Transfer
                                            </x-ui.button>
                                        @endif
                                    @endif

                                    <x-ui.button size="sm" variant="light" class="border py-1 px-1"
                                        href="{{ route('production.labels.batches.print', $item['batch']->id) }}" target="_blank"
                                        title="Print Label">
                                        <i class="feather-printer"></i>
                                    </x-ui.button>

                                    @if($item['can_split'])
                                        <x-ui.button size="sm" variant="light" class="border py-1 px-1"
                                            onclick="openSplitModal({{ $item['batch']->id }}, '{{ $item['batch']->batch_number }}', {{ $item['input_available'] }})"
                                            title="Split Batch">
                                            <i class="feather-git-commit"></i>
                                        </x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                No active batches currently requiring production at this operation.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </x-ui.card>

        {{-- Section 2: Waiting for Transfer Section --}}
        @if(!empty($batchQueue['waiting_transfer']))
            <x-ui.card title="Waiting for Transfer to Next Operation" class="mb-4 border-warning">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th class="text-end">Processed Good Qty</th>
                            <th class="text-end">Already Transferred</th>
                            <th class="text-end">Ready to Transfer</th>
                            <th>Destination</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batchQueue['waiting_transfer'] as $item)
                            <tr>
                                <td><strong class="font-monospace text-dark">{{ $item['batch']->batch_number }}</strong></td>
                                <td class="text-end fw-semibold text-success">{{ number_format($item['good_at_operation'], 2) }}</td>
                                <td class="text-end text-muted">{{ number_format($item['transferred_to_next'], 2) }}</td>
                                <td class="text-end fw-bold text-primary">{{ number_format($item['ready_to_transfer'], 2) }}</td>
                                <td>
                                    <x-ui.badge variant="info" soft class="font-monospace">
                                        {{ $item['next_operation']?->name ?? 'Next Operation' }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    @php
                                        $wipId = \App\Domains\Production\Models\ProductionWip::where('tenant_id', $op->tenant_id)
                                            ->where('production_order_id', $op->production_order_id)
                                            ->where('production_batch_id', $item['batch']->id)
                                            ->value('id');
                                    @endphp
                                    @if($wipId && $item['next_operation'])
                                        <x-ui.button size="sm" variant="warning" class="py-1 px-3"
                                            onclick="openTransferModal({{ $wipId }}, {{ $item['batch']->id }}, '{{ $item['batch']->batch_number }}', {{ $op->routing_operation_id }}, {{ $item['next_operation']->routing_operation_id }}, '{{ $item['next_operation']->name }}', {{ $item['ready_to_transfer'] }})">
                                            <i class="feather-send me-1"></i> Transfer WIP
                                        </x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </x-ui.card>
        @endif

        {{-- Section 3: Blocked / On Hold / Rework --}}
        @if(!empty($batchQueue['blocked']))
            <x-ui.card title="Blocked / Quality Hold / Rework" class="mb-4 border-danger">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th class="text-end">Scrap</th>
                            <th class="text-end">Rework</th>
                            <th>Status / Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batchQueue['blocked'] as $item)
                            <tr>
                                <td><strong class="font-monospace text-dark">{{ $item['batch']->batch_number }}</strong></td>
                                <td class="text-end text-danger fw-semibold">{{ number_format($item['scrap_at_operation'], 2) }}</td>
                                <td class="text-end text-warning fw-semibold">{{ number_format($item['rework_at_operation'], 2) }}</td>
                                <td>
                                    <x-ui.badge variant="danger" soft class="fs-11">{{ $item['display_status'] }}</x-ui.badge>
                                    <span class="text-muted fs-11 ms-2">Quality intervention required</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </x-ui.card>
        @endif

        {{-- Section 4: Waiting for Predecessor Output --}}
        @if(!$batchQueue['meta']['is_first_op'] && !empty($batchQueue['waiting_input']))
            <x-ui.alert variant="secondary" icon="feather-clock" class="mb-4">
                <strong class="text-dark">Waiting for Predecessor Output:</strong>
                <span class="text-muted fs-13">
                    {{ count($batchQueue['waiting_input']) }} batch(es) are currently processing at
                    <strong class="text-dark">{{ $batchQueue['meta']['previous_op']?->name ?? 'predecessor operation' }}</strong>
                    and have not yet transferred WIP into this operation.
                </span>
            </x-ui.alert>
        @endif

        {{-- Section 5: Completed at This Operation History (Collapsible) --}}
        @if(!empty($batchQueue['completed']))
            <div class="accordion mb-4" id="completedBatchesAccordion">
                <div class="accordion-item border rounded">
                    <h2 class="accordion-header" id="headingCompleted">
                        <button class="accordion-button collapsed bg-light text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompleted" aria-expanded="false" aria-controls="collapseCompleted">
                            <i class="feather-check-circle text-success me-2"></i>
                            Completed at this Operation ({{ count($batchQueue['completed']) }} Batches)
                        </button>
                    </h2>
                    <div id="collapseCompleted" class="accordion-collapse collapse" aria-labelledby="headingCompleted" data-bs-parent="#completedBatchesAccordion">
                        <div class="accordion-body p-0">
                            <x-ui.odoo-form-ui type="table" class="mb-0">
                                <thead>
                                    <tr>
                                        <th>Batch Number</th>
                                        <th class="text-end">Processed Qty</th>
                                        <th class="text-end">Transferred Out</th>
                                        <th>Destination</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($batchQueue['completed'] as $item)
                                        <tr>
                                            <td><strong class="font-monospace text-dark">{{ $item['batch']->batch_number }}</strong></td>
                                            <td class="text-end fw-semibold text-success">{{ number_format($item['processed_at_operation'], 2) }}</td>
                                            <td class="text-end text-dark">{{ number_format($item['transferred_to_next'], 2) }}</td>
                                            <td>
                                                <x-ui.badge variant="info" soft class="font-monospace">
                                                    {{ $item['next_operation']?->name ?? 'Finished Goods Receipt' }}
                                                </x-ui.badge>
                                            </td>
                                            <td class="text-center">
                                                <x-ui.badge variant="success" soft class="fs-10">COMPLETED HERE</x-ui.badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>

    {{-- Create & Merge Batch Controls Column --}}
    <div class="col-lg-4">
        {{-- Create New Batch (Initial Operation Only) --}}
        @if($batchQueue['meta']['is_first_op'])
            @php
                $sumActivePlanned = \App\Domains\Production\Models\ProductionBatch::where('production_order_id', $order->id)
                    ->whereNotIn('status', [\App\Domains\Production\Models\ProductionBatch::STATUS_CANCELLED])
                    ->sum('planned_quantity');
                $unallocatedOrderQty = max(0.0, (float) $order->quantity_ordered - (float) $sumActivePlanned);
            @endphp
            <x-ui.card :title="__('production.create_new_batch')" class="mb-4 border">
                <form method="POST" action="{{ route('production.mes.batches.create') }}">
                    @csrf
                    <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                    <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('production.planned_qty')" name="planned_quantity"
                                inputType="number" step="0.0001" :value="$unallocatedOrderQty" :required="true" />
                            <div class="fs-12 text-muted mt-1">
                                Unallocated balance: <strong>{{ number_format($unallocatedOrderQty, 2) }}</strong> / {{ number_format($order->quantity_ordered, 2) }}
                            </div>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('production.expiry_date')" name="expiry_date"
                                inputType="date" />
                        </div>
                        <div class="col-12 mt-3">
                            <x-ui.button type="submit" variant="primary" icon="feather-plus me-1" class="w-100 py-2">
                                {{ __('production.generate_new_batch') }}
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </x-ui.card>
        @else
            <x-ui.card class="mb-4 bg-light border">
                <div class="fs-13 text-muted">
                    <i class="feather-info text-primary me-1"></i>
                    New production batches are generated at the <strong>initial operation (OP10)</strong> and flow sequentially into this stage via WIP transfers.
                </div>
            </x-ui.card>
        @endif

        {{-- Merge Batches --}}
        @if(count($batches) >= 2)
            <x-ui.card :title="__('production.merge_batches')" class="border">
                <form method="POST" action="{{ route('production.mes.batches.merge') }}">
                    @csrf
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" :label="__('production.select_batches_to_merge')"
                            name="parent_batch_ids[]" :multiple="true" select2Selector="tag" :required="true"
                            helperText="Select 2 or more batches to merge into a single target batch.">
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}" data-qty="{{ $b->planned_quantity }}">{{ $b->batch_number }} (Qty:
                                    {{ number_format($b->planned_quantity, 2) }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('production.target_quantity')"
                                name="target_planned_quantity" id="target_planned_quantity_input" inputType="number"
                                step="0.0001" placeholder="Merged total qty..." :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" :label="__('production.remarks')" name="remarks"
                                placeholder="Comments..." />
                        </div>
                        <div class="col-12 mt-3">
                            <x-ui.button type="submit" variant="warning" icon="feather-git-merge me-1" class="w-100 py-2">
                                {{ __('production.merge_selected_batches') }}
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</div>

{{-- Manual WIP Transfer Modal --}}
<x-ui.modal 
    id="wipTransferModal" 
    title="Transfer WIP to Next Operation" 
    centered="true"
    formAction="#"
    submitText="Execute Transfer"
    closeText="Cancel"
>
    <input type="hidden" name="from_operation_id" id="transferFromOpId">
    <input type="hidden" name="to_operation_id" id="transferToOpId">

    <div class="mb-3 p-3 bg-light rounded text-dark fs-13 border">
        Batch: <strong id="transferBatchNumber" class="font-monospace text-primary"></strong><br>
        Destination: <strong id="transferNextOpName" class="text-dark"></strong><br>
        Ready to Transfer: <strong id="transferAvailableLabel" class="text-success font-monospace"></strong>
    </div>

    <x-ui.odoo-form-ui 
        type="input" 
        label="Transfer Quantity" 
        name="quantity" 
        id="transferQuantityInput" 
        inputType="number" 
        step="0.0001" 
        :required="true" 
    />
    <x-ui.odoo-form-ui 
        type="textarea" 
        label="Remarks" 
        name="remarks" 
        placeholder="Optional transfer comments..." 
        rows="2" 
    />
</x-ui.modal>

{{-- Split Batch Modal --}}
<x-ui.modal id="splitBatchModal" :title="__('production.split_batch')" centered="true"
    formAction="{{ route('production.mes.batches.split') }}" submitText="Apply Split" closeText="Cancel">
    <input type="hidden" name="parent_batch_id" id="splitParentId">
    <div class="p-3 mb-3 bg-light rounded text-dark fs-13 border">
        Splitting Batch: <strong id="splitBatchName" class="font-monospace text-primary"></strong>
        <span class="ms-3">Available Input Qty: <strong id="splitTotalLabel" class="text-dark"></strong></span>
    </div>

    <div id="splitsContainer">
        <div class="row g-3 mb-3">
            <div class="col-5">
                <x-ui.odoo-form-ui type="input" label="Child Qty 1" name="splits[0][planned_quantity]"
                    inputType="number" step="0.0001" placeholder="Qty..." :required="true" />
            </div>
            <div class="col-7">
                <x-ui.odoo-form-ui type="input" :label="__('production.remarks')" name="splits[0][remarks]"
                    placeholder="Optional comments..." />
            </div>
        </div>
        <div class="row g-3 mb-2">
            <div class="col-5">
                <x-ui.odoo-form-ui type="input" label="Child Qty 2" name="splits[1][planned_quantity]"
                    inputType="number" step="0.0001" placeholder="Qty..." :required="true" />
            </div>
            <div class="col-7">
                <x-ui.odoo-form-ui type="input" :label="__('production.remarks')" name="splits[1][remarks]"
                    placeholder="Optional comments..." />
            </div>
        </div>
    </div>
</x-ui.modal>

<script>
    function openLogProgressForBatch(batchId, batchNumber, remainingQty) {
        let modalEl = document.getElementById('logProgressModal');
        if (modalEl) {
            let batchSelect = modalEl.querySelector('select[name="production_batch_id"]');
            if (batchSelect) {
                batchSelect.value = batchId;
            }
            let prodInput = modalEl.querySelector('#log_producedInput');
            if (prodInput) {
                prodInput.value = remainingQty > 0 ? remainingQty : 0;
            }
            let modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    function openTransferModal(wipId, batchId, batchNumber, fromOpId, toOpId, nextOpName, readyQty) {
        let form = document.getElementById('wipTransferModal').querySelector('form');
        if (form) {
            form.action = '{{ url('production/wip') }}/' + wipId + '/transfer';

            let batchInput = form.querySelector('input[name="production_batch_id"]');
            if (!batchInput) {
                batchInput = document.createElement('input');
                batchInput.type = 'hidden';
                batchInput.name = 'production_batch_id';
                form.appendChild(batchInput);
            }
            batchInput.value = batchId;
        }
        document.getElementById('transferFromOpId').value = fromOpId;
        document.getElementById('transferToOpId').value = toOpId;
        document.getElementById('transferBatchNumber').innerText = batchNumber;
        document.getElementById('transferNextOpName').innerText = nextOpName;
        document.getElementById('transferAvailableLabel').innerText = readyQty;
        document.getElementById('transferQuantityInput').value = readyQty;

        let modalEl = document.getElementById('wipTransferModal');
        if (modalEl) {
            let modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    function openSplitModal(id, number, qty) {
        document.getElementById('splitParentId').value = id;
        document.getElementById('splitBatchName').innerText = number;
        document.getElementById('splitTotalLabel').innerText = qty;

        let modalEl = document.getElementById('splitBatchModal');
        if (modalEl) {
            let modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        $(document).on('change.select2 change', 'select[name="parent_batch_ids[]"]', function () {
            let totalQty = 0;
            $(this).find('option:selected').each(function () {
                let qty = parseFloat($(this).data('qty')) || 0;
                totalQty += qty;
            });
            let targetInput = document.getElementById('target_planned_quantity_input');
            if (targetInput && totalQty > 0) {
                targetInput.value = totalQty.toFixed(4);
            }
        });
    });
</script>