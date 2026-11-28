@extends('layouts.duralux')

@section('title', 'Operation QC | SaaS ERP')
@section('page-title', 'Quality inspection — Op #' . $orderOp->operation_number)
@section('breadcrumb', 'Operation QC')

{{-- QD (docs/production_quality_rules.md, user 2026-11-28): an operation's QC is done here. One inspection record = one
     quantity with one result; units with another result go in another record of the same operation. --}}
@section('content')
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
    $ruleTip = 'One inspection record = one quantity with one result. Every check is taken for that quantity: if all checks pass, the whole quantity passes; if any check fails, the whole quantity fails. Units with another result go in a separate record of the same operation (for example 20 passed in one record, 20 failed in another).';
    $backUrl = $source === 'shop_floor' ? route('production.mes.dashboard')
        : ($source === 'operator' ? route('production.mes.operator.execution', $orderOp->id) : route('production.orders.show', $orderOp->production_order_id));
    $product = $orderOp->sourceProduct ?? $order?->product;
@endphp
<div class="erp-single-panel bg-white">

    @if(session('success')) <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" /> @endif
    @if(session('error')) <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" /> @endif
    @if(session('warning')) <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" /> @endif

    <x-ui.odoo-form-ui type="sheet">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <x-ui.button href="{{ $backUrl }}" variant="outline-secondary" icon="feather-arrow-left">{{ $source === 'shop_floor' ? 'Shop floor' : ($source === 'operator' ? 'Operator screen' : 'Order') }}</x-ui.button>
                <span class="fw-bold fs-14 text-dark"><i class="feather-shield text-warning me-1"></i>Quality inspection</span>
                <span class="badge bg-light text-dark border fs-11" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ $ruleTip }}">
                    <i class="feather-info me-1"></i>How records work
                </span>
                @if($orderOp->is_external)<span class="badge bg-soft-info text-info border">Outsourced — {{ $orderOp->vendor?->name ?? 'vendor' }}</span>@endif
            </div>
            <form method="POST" action="{{ route('production.quality.operation-inspection.add', $orderOp->id) }}">
                @csrf
                <input type="hidden" name="source" value="{{ $source }}">
                <button type="submit" class="btn btn-sm btn-outline-primary" title="Add another open inspection record for this operation, when the process needs it">
                    <i class="feather-plus me-1"></i>Add inspection
                </button>
            </form>
        </div>

        <div class="row g-4 mb-4 fs-13 text-dark">
            <div class="col-md-6 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Production</h6>
                @if($order)
                    <div>Order <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary font-monospace">{{ $order->order_number }}</a></div>
                @endif
                <div class="fs-12 mt-1">Op #{{ $orderOp->operation_number }} — {{ $orderOp->name }}</div>
                <div class="fs-12 text-muted mt-1">Item: {{ $product ? trim(($product->sku ? $product->sku . ' — ' : '') . $product->name) : '—' }}</div>
            </div>
            <div class="col-md-6">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Quantities</h6>
                <div class="d-flex flex-wrap gap-2 font-monospace fs-12">
                    <span class="badge bg-light text-dark border" title="Target good output of this operation">Target {{ $fmt($target) }}</span>
                    <span class="badge {{ $waiting > 0 ? 'bg-soft-warning text-dark border border-warning' : 'bg-light text-muted border' }}" title="Logged (or received) and not inspected yet">Waiting for QC {{ $fmt($waiting) }}</span>
                    <span class="badge bg-soft-success text-success border" title="{{ $ruleTip }}">Passed {{ $fmt($passedQty) }}</span>
                    <span class="badge bg-soft-danger text-danger border" title="{{ $ruleTip }}">Failed {{ $fmt($failedQty) }}</span>
                </div>
                @if($waiting <= 0)
                    <div class="fs-12 text-muted mt-2"><i class="feather-info me-1"></i>No units are waiting. Units come here once output is logged{{ $orderOp->is_external ? ' or received from the vendor' : '' }}.</div>
                @endif
            </div>
        </div>

        {{-- Open record(s) --}}
        @if($openRecords->count() > 1)
            <div class="d-flex flex-wrap gap-2 mb-2 fs-12">
                <span class="text-muted">Open records:</span>
                @foreach($openRecords as $rec)
                    <a href="{{ route('production.quality.operation-inspection.show', ['op' => $orderOp->id, 'inspection' => $rec->id, 'source' => $source]) }}"
                       class="badge {{ $active && $active->id === $rec->id ? 'bg-primary text-white' : 'bg-light text-dark border' }} font-monospace text-decoration-none">{{ $rec->inspection_number }}</a>
                @endforeach
            </div>
        @endif

        @if($active)
            <div class="border rounded p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0 fs-13">Perform <span class="font-monospace">{{ $active->inspection_number }}</span></h6>
                    <span class="fs-11 text-muted">{{ $checklistPlans->count() }} plan(s) · {{ $checklistPlans->where('is_mandatory', true)->count() }} mandatory</span>
                </div>
                <form method="POST" action="{{ route('production.quality.operation-inspection.perform', $active->id) }}">
                    @csrf
                    <input type="hidden" name="source" value="{{ $source }}">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold fs-12">Quantity in this record <span class="text-danger">*</span>
                                <i class="feather-info text-muted ms-1" data-bs-toggle="tooltip" title="{{ $ruleTip }}"></i></label>
                            <input type="number" step="any" min="0.0001" max="{{ (float) $waiting }}" name="quantity" class="form-control form-control-sm font-monospace"
                                   value="{{ old('quantity', $waiting > 0 ? $fmt($waiting) : '') }}" required @disabled($waiting <= 0)>
                            <div class="form-text fs-11">Up to {{ $fmt($waiting) }} waiting.</div>
                        </div>
                        @if($checklistPlans->isEmpty())
                            <div class="col-md-4">
                                <label class="form-label fw-semibold fs-12">Result <span class="text-danger">*</span></label>
                                <select name="result" class="form-select form-select-sm" required @disabled($waiting <= 0)>
                                    <option value="">— choose —</option>
                                    <option value="passed" @selected(old('result') === 'passed')>Passed</option>
                                    <option value="failed" @selected(old('result') === 'failed')>Failed</option>
                                </select>
                                <div class="form-text fs-11">No quality plan is attached to this operation.</div>
                            </div>
                        @endif
                    </div>

                    @if($checklistPlans->isNotEmpty())
                        <div class="mb-3">
                            <div class="fw-semibold fs-12 mb-2"><i class="feather-check-square text-primary me-1"></i>Checks (taken for the whole quantity; any failed check fails the record)</div>
                            @include('modules.production.quality.partials.checklist-inputs', ['checklistPlans' => $checklistPlans, 'idPrefix' => 'opqc' . $active->id])
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-12">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Observations, gauge used, lot details…">{{ old('remarks') }}</textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning text-dark fw-bold px-4" @disabled($waiting <= 0)>
                            <i class="feather-check-circle me-1"></i>Submit inspection
                        </button>
                    </div>
                </form>
            </div>
        @elseif($orderOp->status === \App\Domains\Production\Models\ProductionOrderOperation::STATUS_COMPLETED)
            <div class="alert alert-light border fs-12 mb-4"><i class="feather-check-circle text-success me-1"></i>The operation is completed and nothing is waiting for QC.</div>
        @endif

        {{-- Performed records --}}
        <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Inspection records
            <i class="feather-info ms-1" data-bs-toggle="tooltip" title="{{ $ruleTip }}"></i></h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle fs-12 mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Record</th>
                        <th class="text-end">Quantity</th>
                        <th>Result</th>
                        <th>Checks</th>
                        <th>Inspector</th>
                        <th>Inspected</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $rec)
                        @php
                            $checks = $rec->results;
                            $failedChecks = $checks->where('result', 'failed');
                            $checkTip = $checks->map(function ($r) {
                                $value = $r->recorded_value_numeric ?? ($r->recorded_value_pass === null ? $r->recorded_value_text : ($r->recorded_value_pass ? 'Pass' : 'Fail'));
                                return ($r->parameter?->name ?? 'Check') . ': ' . ($value ?? '—') . ' (' . $r->result . ')';
                            })->implode(' · ');
                        @endphp
                        <tr>
                            <td><a href="{{ route('production.inspections.show', $rec->id) }}" class="font-monospace fw-semibold">{{ $rec->inspection_number ?? ('#' . $rec->id) }}</a></td>
                            <td class="text-end font-monospace">{{ $fmt($rec->inspected_quantity) }}</td>
                            <td>
                                @if($rec->result === 'passed')
                                    <span class="badge bg-soft-success text-success border" title="{{ $ruleTip }}">Passed</span>
                                @elseif($rec->result === 'failed')
                                    <span class="badge bg-soft-danger text-danger border" title="{{ $ruleTip }}">Failed</span>
                                @else
                                    <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', (string) $rec->result)) }}</span>
                                @endif
                            </td>
                            <td><span title="{{ $checkTip }}" data-bs-toggle="tooltip">{{ $checks->count() }} check(s){{ $failedChecks->count() ? ' · ' . $failedChecks->count() . ' failed' : '' }}</span></td>
                            <td>{{ $rec->auditor?->name ?? '—' }}</td>
                            <td class="text-nowrap">{{ $rec->inspected_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit((string) $rec->remarks, 60) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No inspection performed yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.odoo-form-ui>
</div>
@endsection
