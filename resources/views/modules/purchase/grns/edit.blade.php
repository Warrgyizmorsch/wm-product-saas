@extends('layouts.duralux')

@section('title', __('purchase.edit_draft') . " {$grn->grn_number} | SaaS ERP")
@section('page-title', __('purchase.edit_draft') . " " . __('purchase.goods_receipt_note'))
@section('breadcrumb')
    <a href="{{ route('grns.index') }}">{{ __('purchase.goods_receipt_notes') }}</a> &gt; {{ __('purchase.edit') }} {{ $grn->grn_number }}
@endsection

@section('page-actions')
    <a href="{{ route('grns.show', $grn->id) }}" class="btn btn-light border fs-12">
        <i class="feather-arrow-left me-2"></i>{{ __('purchase.back_to_view') }}
    </a>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp
    <div class="row text-dark">
        <div class="col-12">

            <form action="{{ route('grns.update', $grn->id) }}" method="POST" id="grnEditForm">
                @csrf
                @method('PUT')

                <div class="card border-0 shadow-sm bg-white mb-4 odoo-sheet">
                    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-edit text-warning me-2"></i>{{ __('purchase.edit_draft') }} {{ __('purchase.grn') }}</h5>
                            <span class="badge bg-soft-warning text-warning px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $grn->grn_number }}</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary text-white fw-bold px-4 py-2">
                                <i class="feather-save me-1.5"></i>{{ __('purchase.update_draft') }}
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="row g-3 fs-13 pb-4 border-bottom">
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary mb-3">{{ __('purchase.po_supplier_details') }}</h6>

                                <x-ui.odoo-form-ui type="input" label="{{ __('purchase.purchase_order') }}" name="po_no" value="{{ $grn->purchaseOrder?->purchase_order_number ?? __('purchase.direct_receipt') }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" label="{{ __('purchase.supplier_vendor') }}" name="vendor_name" value="{{ $grn->vendor?->name }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" label="{{ __('purchase.warehouse') }}" name="wh_name" value="{{ $grn->warehouse?->name ?? __('purchase.main_warehouse') }}" readonly="true" />
                                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.receipt_date') }}" name="received_date" id="received_date" value="{{ old('received_date', $grn->received_date ? $grn->received_date->format('Y-m-d') : date('Y-m-d')) }}" :required="true" :error-text="$errors->first('received_date')" />
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-3">{{ __('purchase.challan_logistics_details') }}</h6>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.challan_invoice_no') }}" name="challan_number" id="challan_number" value="{{ old('challan_number', $grn->challan_number) }}" placeholder="{{ __('purchase.supplier_challan_no') }}" :error-text="$errors->first('challan_number')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.challan_date') }}" name="challan_date" id="challan_date" value="{{ old('challan_date', $grn->challan_date ? $grn->challan_date->format('Y-m-d') : '') }}" :error-text="$errors->first('challan_date')" />
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <x-ui.odoo-form-ui type="select" label="Transporter Name" name="transporter_id" id="transporter_id" :error-text="$errors->first('transporter_id')">
                                        <option value="">-- Choose Transporter --</option>
                                        <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New Transporter</option>
                                        @foreach($transporters as $transporter)
                                            <option value="{{ $transporter->id }}" @selected(old('transporter_id', $grn->transporter_id) == $transporter->id)>
                                                {{ $transporter->name }} @if($transporter->transporter_id) ({{ $transporter->transporter_id }}) @endif
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.vehicle_number') }}" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number', $grn->vehicle_number) }}" placeholder="e.g. MH-12-AB-1234" :error-text="$errors->first('vehicle_number')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.lr_number') }}" name="lr_number" id="lr_number" value="{{ old('lr_number', $grn->lr_number) }}" placeholder="Lorry Receipt / Docket No" :error-text="$errors->first('lr_number')" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 mb-4">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.store_receipt_remarks') }}" name="notes" placeholder="{{ __('purchase.store_receipt_remarks_placeholder') }}" rows="2" :error-text="$errors->first('notes')">{{ old('notes', $grn->notes) }}</x-ui.odoo-form-ui>
                        </div>

                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-layers text-primary me-2"></i>{{ __('purchase.received_products_matrix') }}</h6>
                                <span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $grn->items->count() }} {{ __('purchase.items') }}</span>
                            </div>

                            <div class="table-responsive border rounded bg-white">
                                <table class="table table-bordered align-middle mb-0 fs-12 text-dark">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 25%;">{{ __('purchase.product_description') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.ordered') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.prev_received') }}</th>
                                            <th style="width: 11%;" class="text-center">{{ __('purchase.receive_qty') }} <span class="text-danger">*</span></th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.reject_qty') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.accepted') }}</th>
                                            <th style="width: 10%;" class="text-end">{{ __('purchase.unit_rate') }}</th>
                                            <th style="width: 12%;" class="text-end">{{ __('purchase.total_amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grn->items as $idx => $item)
                                            <tr class="grn-item-row" data-idx="{{ $idx }}">
                                                <td class="text-center fw-semibold text-muted">{{ $idx + 1 }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                                            <div class="fs-11 text-muted">UOM: <strong>{{ $item->product?->uom?->name ?? 'Pcs' }}</strong></div>
                                                        </div>
                                                        <button type="button" class="btn btn-xs {{ $item->remarks ? 'bg-soft-danger text-danger' : 'bg-soft-primary text-primary' }} border-0 btn-toggle-remark ms-2 px-2 py-1 rounded-pill fw-semibold" data-target="#remark_row_{{ $idx }}">
                                                            <i class="{{ $item->remarks ? 'feather-minus' : 'feather-plus' }} me-1 fs-11"></i><span class="btn-lbl">{{ $item->remarks ? __('purchase.hide_lbl') : __('purchase.note_lbl') }}</span>
                                                        </button>
                                                    </div>
                                                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $item->id }}">
                                                </td>
                                                <td class="text-center font-monospace">{{ number_format($item->ordered_qty, 2) }}</td>
                                                <td class="text-center font-monospace text-muted">{{ number_format($item->previous_received_qty, 2) }}</td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="odoo-table-input text-center font-monospace fw-bold input-receive" name="items[{{ $idx }}][received_qty]" value="{{ (float)$item->received_qty }}" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="odoo-table-input text-center font-monospace input-reject text-danger" name="items[{{ $idx }}][rejected_qty]" value="{{ (float)$item->rejected_qty }}">
                                                </td>
                                                <td class="text-center font-monospace fw-bold text-success cell-accepted">{{ number_format($item->accepted_qty, 2) }}</td>
                                                <td class="text-end font-monospace">{{ number_format($item->unit_rate, 2) }}</td>
                                                <td class="text-end font-monospace fw-bold text-dark cell-total">{{ number_format($item->total_amount, 2) }}</td>
                                            </tr>
                                            <tr id="remark_row_{{ $idx }}" class="remark-row bg-white" style="{{ $item->remarks ? '' : 'display: none;' }}">
                                                <td class="border-0"></td>
                                                <td colspan="8" class="py-2 px-3 border-top-0">
                                                    <div class="p-3 rounded-3 border bg-white shadow-xs">
                                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                                            <span class="badge bg-soft-primary text-primary fs-10 fw-bold text-uppercase">
                                                                <i class="feather-message-square me-1"></i>{{ __('purchase.item_remarks_rejection_reason') }}
                                                            </span>
                                                            <span class="fs-11 text-muted">{{ __('purchase.for') }} <strong>{{ $item->product?->name }}</strong></span>
                                                        </div>
                                                        <input type="text" class="odoo-table-input fs-12 text-dark px-2 py-1" name="items[{{ $idx }}][remarks]" value="{{ $item->remarks }}" placeholder="{{ __('purchase.js_enter_rejection_remarks') }} {{ $item->product?->name }}...">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <!-- Quick Transporter Add Modal Component -->
    <x-ui.modal id="quickTransporterModal" title="<i class='feather-truck text-primary me-2'></i>Quick Add Transporter Master" size="lg" :centered="true" :showFooter="false">
        <form id="quickTransporterForm">
            @csrf
            <div class="p-1">
                <!-- Section 1: Basic Logistics Info -->
                <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-1.5"></i>1. Basic Transporter Information</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <x-ui.odoo-form-ui type="input" label="Transporter Name" name="name" placeholder="e.g. V-Trans, TCI Logistics, GATI KWE" :required="true" />
                    </div>
                    <div class="col-md-5">
                        <x-ui.odoo-form-ui type="input" label="Transporter Code" name="code" value="{{ $autoCode }}" placeholder="e.g. TRP-0005" />
                    </div>
                    <div class="col-md-7">
                        <x-ui.odoo-form-ui type="input" label="15-Digit E-Way Transporter ID" name="transporter_id" placeholder="e.g. 27AAACM1234F1Z1" />
                    </div>
                    <div class="col-md-5">
                        <x-ui.odoo-form-ui type="select" label="Transport Mode" name="transport_mode" :searchable="false">
                            <option value="road">Road Transport</option>
                            <option value="rail">Rail Logistics</option>
                            <option value="air">Air Freight</option>
                            <option value="sea">Sea Cargo</option>
                            <option value="multimodal">Multimodal</option>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <hr class="my-3 text-muted opacity-25">

                <!-- Section 2: Taxation & Contact Info -->
                <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-1.5"></i>2. Taxation & Contact Details</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="GSTIN Number" name="gstin" placeholder="e.g. 27AAAAA0000A1Z5" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="PAN Number" name="pan_number" placeholder="e.g. ABCDE1234F" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Phone / Mobile" name="phone" placeholder="Contact number" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" placeholder="dispatch@transporter.com" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="City" name="city" placeholder="City" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="State" name="state" placeholder="State" />
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex justify-content-end align-items-center gap-2 pt-3 border-top mt-3">
                    <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="saveQuickTransporterBtn">
                        <i class="feather-save me-1.5"></i>Save Transporter
                    </button>
                </div>
            </div>
        </form>
    </x-ui.modal>
@endsection

@push('scripts')
<script>
    // Trigger Quick Add Transporter Modal when "+ Add New Transporter" option is selected
    $(document).on('change', '#transporter_id', function() {
        if ($(this).val() === '__ADD_NEW__') {
            $(this).val('');
            if ($(this).data('select2')) {
                $(this).val(null).trigger('change.select2');
            }
            const modalEl = document.getElementById('quickTransporterModal');
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalInstance.show();
        }
    });

    // Quick Transporter AJAX Store
    $(document).on('submit', '#quickTransporterForm', function(e) {
        e.preventDefault();
        const $btn = $('#saveQuickTransporterBtn');
        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: "{{ route('platform.transporters.quick-create') }}",
            type: "POST",
            data: $(this).serialize(),
            success: function(response) {
                $btn.prop('disabled', false).text('Save Transporter');
                if (response.success && response.transporter) {
                    const t = response.transporter;
                    const labelText = t.name + (t.transporter_id ? ` (${t.transporter_id})` : (t.gstin ? ` (${t.gstin})` : ''));
                    const newOpt = new Option(labelText, t.id, true, true);
                    $('#transporter_id').append(newOpt).trigger('change');

                    const modalEl = document.getElementById('quickTransporterModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();
                    $('#quickTransporterForm')[0].reset();
                }
            },
            error: function(err) {
                $btn.prop('disabled', false).text('Save Transporter');
                alert('Error saving transporter: ' + (err.responseJSON?.message || 'Invalid data'));
            }
        });
    });

    $(document).ready(function() {
        $(document).on('click', '.btn-toggle-remark', function() {
            var target = $($(this).data('target'));
            var icon = $(this).find('i');
            var lbl = $(this).find('.btn-lbl');

            target.slideToggle(150);
            if (icon.hasClass('feather-plus')) {
                icon.removeClass('feather-plus').addClass('feather-minus');
                $(this).removeClass('bg-soft-primary text-primary').addClass('bg-soft-danger text-danger');
                lbl.text('{{ __('purchase.hide_lbl') }}');
            } else {
                icon.removeClass('feather-minus').addClass('feather-plus');
                $(this).removeClass('bg-soft-danger text-danger').addClass('bg-soft-primary text-primary');
                lbl.text('{{ __('purchase.note_lbl') }}');
            }
        });
    });
</script>
@endpush
