<div class="variance-tab-content py-3">
    <!-- Order Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 bg-light rounded border">
                <div class="text-muted small">Planned Qty</div>
                <div class="fs-4 fw-bold text-dark">{{ $analysis['planned_quantity'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-light rounded border">
                <div class="text-muted small">Actual Completed Qty</div>
                <div class="fs-4 fw-bold text-primary">{{ $analysis['actual_completed_quantity'] }} (Yield: {{ $analysis['yield_percentage'] }}%)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-light rounded border">
                <div class="text-muted small">Scrap / Rejected</div>
                <div class="fs-4 fw-bold text-danger">{{ $analysis['scrap_quantity'] }} / {{ $analysis['rejected_quantity'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-light rounded border">
                <div class="text-muted small">Overall Time Variance</div>
                <div class="fs-4 fw-bold {{ $analysis['total_time_variance'] > 0 ? 'text-danger' : 'text-success' }}">
                    {{ $analysis['total_time_variance'] > 0 ? '+' : '' }}{{ $analysis['total_time_variance'] }} min
                </div>
                <span class="badge bg-secondary">{{ $analysis['time_classification'] }}</span>
            </div>
        </div>
    </div>

    <!-- Operation Variance Breakdown -->
    <h6 class="fw-bold mb-3">Operation Breakdown</h6>
    <x-ui.odoo-form-ui type="table">
        <thead>
            <tr>
                <th>Op #</th>
                <th>Operation Name</th>
                <th>Planned Duration</th>
                <th>Actual Duration</th>
                <th>Time Variance</th>
                <th>Produced Qty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($analysis['operations'] as $op)
            <tr>
                <td>{{ $op['operation_number'] }}</td>
                <td>{{ $op['name'] }}</td>
                <td>{{ $op['planned_total_time'] }} min</td>
                <td>{{ $op['actual_total_time'] }} min</td>
                <td>
                    <span class="fw-bold {{ $op['total_time_variance'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $op['total_time_variance'] > 0 ? '+' : '' }}{{ $op['total_time_variance'] }} min
                    </span>
                </td>
                <td>{{ $op['actual_quantity'] }} / {{ $op['planned_quantity'] }}</td>
                <td>
                    <span class="badge {{ $op['time_classification'] === 'SIGNIFICANT_VARIANCE' ? 'bg-danger' : ($op['time_classification'] === 'MINOR_VARIANCE' ? 'bg-warning text-dark' : 'bg-success') }}">
                        {{ $op['time_classification'] }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </x-ui.odoo-form-ui>
</div>
