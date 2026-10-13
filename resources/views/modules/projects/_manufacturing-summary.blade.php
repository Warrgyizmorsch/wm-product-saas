@if(isset($manufacturingSummary) && $manufacturingSummary)
<div class="card border mb-4" id="manufacturingSummaryCard">
    <div class="card-header bg-light py-2.5 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="feather-cpu text-primary fs-16"></i>
            <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('projects.manufacturing_summary') ?: 'Manufacturing Summary' }}</h6>
            <span class="badge bg-soft-primary text-primary fs-11">{{ $manufacturingSummary['total_orders'] }} {{ __('projects.production_orders') ?: 'Orders' }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(isset($manufacturingSummary['total_cost']) && $manufacturingSummary['total_cost'] > 0)
                <span class="badge bg-soft-primary text-primary border border-primary-subtle fw-bold fs-12 px-2.5 py-1">
                    <i class="feather-tag me-1"></i>{{ __('projects.mfg_actual_cost') }}: {{ format_currency($manufacturingSummary['total_cost']) }}
                </span>
            @endif
            <span class="fs-12 text-muted">{{ __('projects.overall_progress') ?: 'Progress' }}:</span>
            <span class="badge bg-soft-success text-success fw-bold fs-12">{{ $manufacturingSummary['overall_progress'] }}%</span>
        </div>
    </div>
    <div class="card-body p-3">
        {{-- KPI metric row --}}
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-light border text-center">
                    <span class="d-block text-muted fs-11 text-uppercase fw-semibold">{{ __('projects.production_orders') ?: 'Orders' }}</span>
                    <span class="fs-16 fw-bold text-dark">{{ $manufacturingSummary['total_orders'] }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-soft-success border border-success-subtle text-center">
                    <span class="d-block text-success fs-11 text-uppercase fw-semibold">{{ __('projects.completed_orders') ?: 'Completed' }}</span>
                    <span class="fs-16 fw-bold text-success">{{ $manufacturingSummary['completed_orders'] }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-soft-info border border-info-subtle text-center">
                    <span class="d-block text-info fs-11 text-uppercase fw-semibold">{{ __('projects.in_progress_orders') ?: 'In Progress' }}</span>
                    <span class="fs-16 fw-bold text-info">{{ $manufacturingSummary['in_progress_orders'] }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-soft-secondary border text-center">
                    <span class="d-block text-secondary fs-11 text-uppercase fw-semibold">{{ __('projects.pending_orders') ?: 'Pending' }}</span>
                    <span class="fs-16 fw-bold text-secondary">{{ $manufacturingSummary['pending_orders'] }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-light border text-center">
                    <span class="d-block text-muted fs-11 text-uppercase fw-semibold">{{ __('projects.ordered_quantity') ?: 'Ordered' }}</span>
                    <span class="fs-16 fw-bold text-dark">{{ number_format($manufacturingSummary['ordered_quantity'], 1) }}</span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="p-2 rounded bg-soft-primary border border-primary-subtle text-center">
                    <span class="d-block text-primary fs-11 text-uppercase fw-semibold">{{ __('projects.produced_quantity') ?: 'Produced' }}</span>
                    <span class="fs-16 fw-bold text-primary">{{ number_format($manufacturingSummary['produced_quantity'], 1) }}</span>
                </div>
            </div>
        </div>

        {{-- Overall progress bar --}}
        <div class="d-flex align-items-center justify-content-between mb-1.5 fs-12">
            <span class="text-muted fw-semibold">{{ __('projects.overall_progress') ?: 'Manufacturing Completion' }}</span>
            <span class="fw-bold text-dark">{{ number_format($manufacturingSummary['produced_quantity'], 1) }} / {{ number_format($manufacturingSummary['ordered_quantity'], 1) }} ({{ $manufacturingSummary['overall_progress'] }}%)</span>
        </div>
        <div class="progress ht-8 mb-3">
            <div class="progress-bar bg-success" style="width: {{ $manufacturingSummary['overall_progress'] }}%"></div>
        </div>

        {{-- Orders table --}}
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                <thead class="bg-light text-uppercase fs-11 text-muted">
                    <tr>
                        <th style="width: 18%">{{ __('production.order_number') ?: 'Order #' }}</th>
                        <th style="width: 28%">{{ __('production.finished_product') ?: 'Product' }}</th>
                        <th style="width: 22%">{{ __('projects.task') ?: 'Linked Task' }}</th>
                        <th class="text-center" style="width: 14%">{{ __('projects.produced_quantity') ?: 'Produced / Ordered' }}</th>
                        <th class="text-center" style="width: 10%">{{ __('projects.status') ?: 'Status' }}</th>
                        <th class="text-end" style="width: 8%">{{ __('production.action') ?: 'Action' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($manufacturingSummary['orders'] as $order)
                        @php
                            $orderStatusBadge = match ($order->status) {
                                'completed' => 'bg-soft-success text-success border border-success-subtle',
                                'in_progress', 'released' => 'bg-soft-info text-info border border-info-subtle',
                                'cancelled' => 'bg-soft-danger text-danger border border-danger-subtle',
                                default => 'bg-soft-secondary text-secondary border border-secondary-subtle',
                            };
                        @endphp
                        <tr>
                            <td class="fw-bold font-monospace">
                                <a href="{{ route('production.orders.show', $order->id) }}" class="text-primary text-decoration-none" target="_blank">
                                    {{ $order->order_number }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</span>
                                @if($order->product?->sku)
                                    <small class="text-muted font-monospace d-block fs-10">{{ $order->product->sku }}</small>
                                @endif
                            </td>
                            <td>
                                @if($order->projectTask)
                                    <a href="{{ route('projects.tasks.show', [$project, $order->projectTask]) }}" class="text-dark text-decoration-none" target="_blank">
                                        <i class="feather-check-square me-1 text-info fs-11"></i>{{ $order->projectTask->task_code }} — {{ $order->projectTask->title }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-success">{{ number_format($order->quantity_produced, 1) }}</span>
                                <span class="text-muted">/ {{ number_format($order->quantity_ordered, 1) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $orderStatusBadge }} fs-10 text-uppercase">{{ $order->status }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-xs btn-outline-secondary" target="_blank">
                                    <i class="feather-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
