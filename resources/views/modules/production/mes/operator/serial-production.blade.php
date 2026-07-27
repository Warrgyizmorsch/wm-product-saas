<div class="row g-4 mt-2">
    {{-- Serials List --}}
    <div class="col-lg-6">
        <x-ui.card :title="__('production.registered_serials')" style="max-height: 480px; overflow-y: auto;">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 40%">{{ __('production.serial_number') }}</th>
                        <th style="width: 20%">{{ __('production.status') }}</th>
                        <th style="width: 25%">Scanned Tag</th>
                        <th style="width: 15%" class="text-center">{{ __('production.actions') }}</th>
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
                                <span class="badge {{ $badgeClass }} fs-11">{{ strtoupper($serial->status) }}</span>
                            </td>
                            <td class="text-muted font-monospace fs-11">
                                {{ $serial->barcode ?? '—' }}
                            </td>
                            <td class="text-center">
                                <x-ui.button size="sm" variant="light" class="border py-1 px-2" href="{{ route('production.labels.serials.print', $serial->id) }}" target="_blank" title="{{ __('production.print_label') }}">
                                    <i class="feather-printer me-1"></i> {{ __('production.print_label') }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                No serial numbers registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </x-ui.card>
    </div>

    {{-- Controls --}}
    <div class="col-lg-6">
        {{-- Range Generation --}}
        <x-ui.card :title="__('production.generate_serial_range')" class="mb-4">
            <form method="POST" action="{{ route('production.mes.serials.generate') }}">
                @csrf
                <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.prefix')" name="prefix" :value="'SN-' . date('Y')" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.quantity')" name="quantity" inputType="number" :value="max(1, $order->quantity_ordered - $serials->count())" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.starting_index')" name="start_num" inputType="number" value="1" :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" :label="__('production.select_batch_optional')" name="batch_id">
                            <option value="">None (Independent Serial)</option>
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}">{{ $b->batch_number }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-12 mt-4">
                        <x-ui.button type="submit" variant="primary" icon="feather-plus-circle me-1" class="w-100 py-2">
                            {{ __('production.generate_range') }}
                        </x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        {{-- Single Manual Registration --}}
        <x-ui.card :title="__('production.register_single_serial')">
            <form method="POST" action="{{ route('production.mes.serials.manual-assign') }}">
                @csrf
                <input type="hidden" name="production_order_id" value="{{ $order->id }}">
                <input type="hidden" name="product_id" value="{{ $order->product_id }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.serial_number')" name="serial_number" placeholder="Enter custom serial..." :required="true" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="select" :label="__('production.select_batch_optional')" name="batch_id">
                            <option value="">None (Independent Serial)</option>
                            @foreach($batches as $b)
                                <option value="{{ $b->id }}">{{ $b->batch_number }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-12 mt-4">
                        <x-ui.button type="submit" variant="light" icon="feather-tag me-1" class="border w-100 py-2">
                            {{ __('production.register_custom_serial') }}
                        </x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
