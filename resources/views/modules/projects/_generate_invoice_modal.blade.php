<x-ui.modal id="modalGenerateInvoice" size="lg" :title="__('projects.generate_invoice')" :showFooter="false">
    <form action="{{ route('projects.billing.store', $project) }}" method="POST" id="formGenerateInvoice">
        @csrf

        {{-- Invoice Header Metadata --}}
        <div class="row g-3 mb-4 pb-3 border-bottom">
            <div class="col-md-4">
                <x-ui.odoo-form-ui
                    type="date"
                    name="invoice_date"
                    :label="__('projects.invoice_date')"
                    :value="old('invoice_date', now()->toDateString())"
                    required
                />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui
                    type="date"
                    name="due_date"
                    :label="__('projects.due_date')"
                    :value="old('due_date', now()->addDays(30)->toDateString())"
                    required
                />
            </div>
            <div class="col-md-4">
                <x-ui.odoo-form-ui
                    type="text"
                    name="payment_terms"
                    :label="__('projects.payment_terms')"
                    :value="old('payment_terms', 'Net 30')"
                    placeholder="e.g. Net 30"
                />
            </div>
            <div class="col-md-12">
                <x-ui.odoo-form-ui
                    type="select"
                    id="modalServiceProductSelect"
                    name="service_product_id"
                    :label="__('projects.service_product')"
                    :required="true"
                    :searchable="false"
                    :helperText="__('projects.service_product_hint')"
                >
                    @if ($serviceProducts->isEmpty())
                        <option value="">{{ __('projects.no_service_products_found') }}</option>
                    @else
                        @foreach ($serviceProducts as $prod)
                            <option value="{{ $prod->id }}" @selected((int) old('service_product_id') === $prod->id)>
                                {{ $prod->name }} ({{ $prod->sku }}) — Rate: {{ format_currency($prod->selling_price) }}
                            </option>
                        @endforeach
                    @endif
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-12">
                <x-ui.odoo-form-ui
                    type="textarea"
                    name="notes"
                    :label="__('projects.invoice_notes')"
                    :value="old('notes', 'Invoice for deliverables on ' . $project->name)"
                    rows="2"
                    placeholder="{{ __('projects.invoice_notes_placeholder') }}"
                />
            </div>
        </div>

        {{-- Unbilled Time Logs Selection --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="feather-clock me-2 text-primary"></i>
                    {{ __('projects.unbilled_time_logs') }}
                    <span class="badge bg-secondary-subtle text-secondary ms-2 fs-11">{{ $unbilledTimeLogs->count() }}</span>
                </h6>
                @if ($unbilledTimeLogs->isNotEmpty())
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fs-12" id="btnToggleAllTimeLogs">
                        {{ __('projects.select_all') }}
                    </button>
                @endif
            </div>

            @if ($unbilledTimeLogs->isEmpty())
                <div class="p-3 bg-light rounded text-center text-muted fs-12">
                    {{ __('projects.no_unbilled_time_logs') }}
                </div>
            @else
                <div class="table-responsive border rounded" style="max-height: 220px; overflow-y: auto;">
                    <x-ui.odoo-form-ui type="table" tableClass="table table-sm table-hover align-middle mb-0 fs-12">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 36px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkAllTimeLogs">
                                </th>
                                <th>{{ __('projects.task') }}</th>
                                <th>{{ __('projects.member') }}</th>
                                <th>{{ __('projects.date') }}</th>
                                <th class="text-end">{{ __('projects.hours') }}</th>
                                <th class="text-end">{{ __('projects.rate') }} ({{ active_currency_symbol() }})</th>
                                <th class="text-end pe-3">{{ __('projects.amount') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unbilledTimeLogs as $log)
                                @php
                                    $amount = (float)$log->hours * (float)($log->hourly_rate ?? 0);
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="time_log_ids[]" value="{{ $log->id }}"
                                               data-amount="{{ $amount }}" class="form-check-input check-time-log">
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $log->task?->title ?? '—' }}</td>
                                    <td>{{ $log->user?->name ?? '—' }}</td>
                                    <td>{{ $log->log_date ? $log->log_date->format('d/m/Y') : '—' }}</td>
                                    <td class="text-end">{{ number_format((float)$log->hours, 2) }}</td>
                                    <td class="text-end">{{ format_currency($log->hourly_rate ?? 0) }}</td>
                                    <td class="text-end pe-3 fw-bold">{{ format_currency($amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            @endif
        </div>

        {{-- Completed Milestones Selection --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="feather-flag me-2 text-primary"></i>
                    {{ __('projects.unbilled_milestones') }}
                    <span class="badge bg-secondary-subtle text-secondary ms-2 fs-11">{{ $unbilledMilestones->count() }}</span>
                </h6>
                @if ($unbilledMilestones->isNotEmpty())
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fs-12" id="btnToggleAllMilestones">
                        {{ __('projects.select_all') }}
                    </button>
                @endif
            </div>

            @if ($unbilledMilestones->isEmpty())
                <div class="p-3 bg-light rounded text-center text-muted fs-12">
                    {{ __('projects.no_unbilled_milestones') }}
                </div>
            @else
                <div class="table-responsive border rounded" style="max-height: 200px; overflow-y: auto;">
                    <x-ui.odoo-form-ui type="table" tableClass="table table-sm table-hover align-middle mb-0 fs-12">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 36px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="checkAllMilestones">
                                </th>
                                <th>{{ __('projects.milestone') }}</th>
                                <th>{{ __('projects.description') }}</th>
                                <th class="text-end pe-3">{{ __('projects.billing_amount') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unbilledMilestones as $ms)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="milestone_ids[]" value="{{ $ms->id }}"
                                               data-amount="{{ (float)$ms->billing_amount }}" class="form-check-input check-milestone">
                                    </td>
                                    <td class="fw-semibold text-dark">{{ $ms->name }}</td>
                                    <td class="text-muted">{{ Str::limit($ms->description ?? '—', 50) }}</td>
                                    <td class="text-end pe-3 fw-bold text-primary">{{ format_currency($ms->billing_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            @endif
        </div>

        {{-- Live Summary Calculation Box --}}
        <div class="p-3 bg-light rounded border d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="text-muted fs-12 d-block">{{ __('projects.selected_deliverables') }}</span>
                <span class="fw-bold fs-14 text-dark" id="txtSelectedCount">0 {{ __('projects.items_selected', ['count' => 0]) }}</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-12 d-block">{{ __('projects.estimated_subtotal') }}</span>
                <span class="fw-bold fs-16 text-primary" id="txtSelectedSubtotal">{{ format_currency(0) }}</span>
            </div>
        </div>

        <div class="modal-footer px-0 pb-0">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                {{ __('projects.cancel') }}
            </button>
            <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitGenerateInvoice" disabled>
                <i class="feather-check-circle me-1"></i>{{ __('projects.generate_draft_invoice') }}
            </button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAllTimeLogs = document.getElementById('checkAllTimeLogs');
    const checkAllMilestones = document.getElementById('checkAllMilestones');
    const timeLogCheckboxes = document.querySelectorAll('.check-time-log');
    const milestoneCheckboxes = document.querySelectorAll('.check-milestone');
    const txtCount = document.getElementById('txtSelectedCount');
    const txtSubtotal = document.getElementById('txtSelectedSubtotal');
    const btnSubmit = document.getElementById('btnSubmitGenerateInvoice');
    const btnToggleAllTimeLogs = document.getElementById('btnToggleAllTimeLogs');
    const btnToggleAllMilestones = document.getElementById('btnToggleAllMilestones');

    function recalculate() {
        let count = 0;
        let subtotal = 0.0;

        timeLogCheckboxes.forEach(cb => {
            if (cb.checked) {
                count++;
                subtotal += parseFloat(cb.getAttribute('data-amount') || 0);
            }
        });

        milestoneCheckboxes.forEach(cb => {
            if (cb.checked) {
                count++;
                subtotal += parseFloat(cb.getAttribute('data-amount') || 0);
            }
        });

        if (txtCount) {
            const template = count === 1 ? @js(__('projects.item_selected', ['count' => ':count'])) : @js(__('projects.items_selected', ['count' => ':count']));
            txtCount.textContent = template.replace(':count', count);
        }
        if (txtSubtotal) {
            if (window.AppCurrency && typeof window.AppCurrency.format === 'function') {
                txtSubtotal.textContent = window.AppCurrency.format(subtotal);
            } else {
                txtSubtotal.textContent = @json(active_currency_symbol()) + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
        if (btnSubmit) {
            btnSubmit.disabled = (count === 0);
        }
    }

    if (checkAllTimeLogs) {
        checkAllTimeLogs.addEventListener('change', function () {
            timeLogCheckboxes.forEach(cb => { cb.checked = checkAllTimeLogs.checked; });
            recalculate();
        });
    }

    if (btnToggleAllTimeLogs) {
        btnToggleAllTimeLogs.addEventListener('click', function () {
            const anyUnchecked = Array.from(timeLogCheckboxes).some(cb => !cb.checked);
            timeLogCheckboxes.forEach(cb => { cb.checked = anyUnchecked; });
            if (checkAllTimeLogs) checkAllTimeLogs.checked = anyUnchecked;
            recalculate();
        });
    }

    if (checkAllMilestones) {
        checkAllMilestones.addEventListener('change', function () {
            milestoneCheckboxes.forEach(cb => { cb.checked = checkAllMilestones.checked; });
            recalculate();
        });
    }

    if (btnToggleAllMilestones) {
        btnToggleAllMilestones.addEventListener('click', function () {
            const anyUnchecked = Array.from(milestoneCheckboxes).some(cb => !cb.checked);
            milestoneCheckboxes.forEach(cb => { cb.checked = anyUnchecked; });
            if (checkAllMilestones) checkAllMilestones.checked = anyUnchecked;
            recalculate();
        });
    }

    timeLogCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            if (checkAllTimeLogs) {
                checkAllTimeLogs.checked = Array.from(timeLogCheckboxes).every(c => c.checked);
            }
            recalculate();
        });
    });

    milestoneCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            if (checkAllMilestones) {
                checkAllMilestones.checked = Array.from(milestoneCheckboxes).every(c => c.checked);
            }
            recalculate();
        });
    });

    recalculate();
});
</script>
@endpush
