<div class="row g-4 mt-2">
    {{-- Active Batches List --}}
    <div class="col-lg-6">
        <x-ui.card :title="__('production.active_batches')">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 30%">{{ __('production.batch_number') }}</th>
                        <th style="width: 20%" class="text-end">{{ __('production.planned_qty') }}</th>
                        <th style="width: 20%" class="text-end">{{ __('production.actual_qty') }}</th>
                        <th style="width: 15%" class="text-center">{{ __('production.status') }}</th>
                        <th style="width: 15%" class="text-center">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark font-monospace">{{ $batch->batch_number }}</div>
                                @if($batch->barcode)
                                    <div class="fs-10 text-muted"><i class="feather-tag me-1"></i>{{ $batch->barcode }}</div>
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
                                <span class="badge {{ $badgeClass }} fs-11">{{ strtoupper($batch->status) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <x-ui.button size="sm" variant="light" class="border py-1 px-2" href="{{ route('production.labels.batches.print', $batch->id) }}" target="_blank" title="{{ __('production.print_label') }}">
                                        <i class="feather-printer me-1"></i> {{ __('production.print_label') }}
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="light" class="border py-1 px-2" onclick="openSplitModal({{ $batch->id }}, '{{ $batch->batch_number }}', {{ $batch->planned_quantity }})">
                                        {{ __('production.split_batch') }}
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                No active batches created for this order yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </x-ui.card>
    </div>

    {{-- Create & Merge Operations --}}
    <div class="col-lg-6">
        {{-- Create New Batch --}}
        <x-ui.card :title="__('production.create_new_batch')" class="mb-4">
            <form method="POST" action="{{ route('production.mes.batches.create') }}">
                @csrf
                <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui 
                            type="input" 
                            :label="__('production.planned_qty')" 
                            name="planned_quantity" 
                            inputType="number" 
                            step="0.0001"
                            :value="$order->quantity_ordered" 
                            :required="true" 
                        />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui 
                            type="input" 
                            :label="__('production.expiry_date')" 
                            name="expiry_date" 
                            inputType="date" 
                        />
                    </div>
                    <div class="col-12 mt-4">
                        <x-ui.button type="submit" variant="primary" icon="feather-plus me-1" class="w-100 py-2">
                            {{ __('production.generate_new_batch') }}
                        </x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        {{-- Merge Batches --}}
        @if($batches->count() >= 2)
            <x-ui.card :title="__('production.merge_batches')">
                <form method="POST" action="{{ route('production.mes.batches.merge') }}">
                    @csrf
                    <div class="mb-3">
                        <x-ui.odoo-form-ui 
                            type="select" 
                            :label="__('production.select_batches_to_merge')" 
                            name="parent_batch_ids[]" 
                            :multiple="true" 
                            select2Selector="tag" 
                            :required="true" 
                            helperText="Select 2 or more batches to merge into a single target batch."
                        >
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}" data-qty="{{ $b->planned_quantity }}">{{ $b->batch_number }} (Qty: {{ number_format($b->planned_quantity, 2) }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                :label="__('production.target_quantity')" 
                                name="target_planned_quantity" 
                                id="target_planned_quantity_input"
                                inputType="number" 
                                step="0.0001"
                                placeholder="Merged total qty..." 
                                :required="true" 
                                helperText="Auto-calculated sum of selected source batch quantities."
                            />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui 
                                type="input" 
                                :label="__('production.remarks')" 
                                name="remarks" 
                                placeholder="Comments..." 
                            />
                        </div>
                        <div class="col-12 mt-4">
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

{{-- Split Batch Modal --}}
<x-ui.modal 
    id="splitBatchModal" 
    :title="__('production.split_batch')" 
    centered="true" 
    formAction="{{ route('production.mes.batches.split') }}" 
    submitText="Apply Split" 
    closeText="Cancel"
>
    <input type="hidden" name="parent_batch_id" id="splitParentId">
    <div class="p-3 mb-3 bg-light rounded text-dark fs-13">
        Splitting Batch: <strong id="splitBatchName" class="font-monospace text-primary"></strong>
        <span class="ms-3">Total Qty: <strong id="splitTotalLabel" class="text-dark"></strong></span>
    </div>

    <div id="splitsContainer">
        <div class="row g-3 mb-3">
            <div class="col-5">
                <x-ui.odoo-form-ui type="input" label="Child Qty 1" name="splits[0][planned_quantity]" inputType="number" step="0.0001" placeholder="Qty..." :required="true" />
            </div>
            <div class="col-7">
                <x-ui.odoo-form-ui type="input" :label="__('production.remarks')" name="splits[0][remarks]" placeholder="Optional comments..." />
            </div>
        </div>
        <div class="row g-3 mb-2">
            <div class="col-5">
                <x-ui.odoo-form-ui type="input" label="Child Qty 2" name="splits[1][planned_quantity]" inputType="number" step="0.0001" placeholder="Qty..." :required="true" />
            </div>
            <div class="col-7">
                <x-ui.odoo-form-ui type="input" :label="__('production.remarks')" name="splits[1][remarks]" placeholder="Optional comments..." />
            </div>
        </div>
    </div>
</x-ui.modal>

<script>
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
