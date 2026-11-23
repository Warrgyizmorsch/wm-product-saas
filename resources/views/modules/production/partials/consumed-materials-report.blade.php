{{-- Temp no-BOM flow: materials consumed on an order — by operation, by material, and every logged line. --}}
@if(config('production.temp_flow'))
    @php
        $cmRows = collect(app(\App\Domains\Production\Services\ProductionMaterialService::class)->consumptionReport($order));
        $cmByOperation = $cmRows->groupBy(fn ($r) => $r['operation_id'])->map(fn ($g) => [
            'operation' => trim(($g->first()['operation_number'] ?? '') . ' ' . ($g->first()['operation_name'] ?? '')),
            'lines' => $g->count(), 'logs' => $g->pluck('log_id')->unique()->count(),
            'items' => $g->groupBy('product_id')->map(fn ($m) => ['name' => $m->first()['product_name'], 'unit' => $m->first()['unit'], 'qty' => $m->sum('quantity')])->values(),
            'cost' => $g->sum('cost'),
        ]);
        $cmByMaterial = $cmRows->groupBy('product_id')->map(fn ($g) => [
            'name' => $g->first()['product_name'], 'sku' => $g->first()['sku'], 'unit' => $g->first()['unit'],
            'qty' => $g->sum('quantity'), 'cost' => $g->sum('cost'),
        ])->values();
        $cmFmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');
    @endphp

    <div class="mb-4" id="materials-consumed-report">
        <h5 class="fw-bold text-dark mb-3"><i class="feather-package text-primary me-2"></i>Materials consumed (shop floor logs)</h5>

        @if($cmRows->isEmpty())
            <div class="text-muted fs-12 py-3">No materials have been logged as consumed on this order yet.</div>
        @else
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="fw-bold fs-12 text-uppercase text-muted mb-2">By operation</div>
                    <table class="erp-thin-table">
                        <thead><tr><th>Operation</th><th>Materials consumed</th><th class="text-center">Logs</th><th class="text-end">Cost</th></tr></thead>
                        <tbody>
                            @foreach($cmByOperation as $opRow)
                                <tr>
                                    <td class="fw-semibold">{{ $opRow['operation'] ?: '—' }}</td>
                                    <td>@foreach($opRow['items'] as $it)<div class="fs-12">{{ $it['name'] }} — <strong>{{ $cmFmt($it['qty']) }}</strong> {{ $it['unit'] }}</div>@endforeach</td>
                                    <td class="text-center">{{ $opRow['logs'] }}</td>
                                    <td class="text-end">{{ number_format($opRow['cost'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-lg-6">
                    <div class="fw-bold fs-12 text-uppercase text-muted mb-2">By material (whole order)</div>
                    <table class="erp-thin-table">
                        <thead><tr><th>Material</th><th class="text-end">Total consumed</th><th class="text-end">Cost</th></tr></thead>
                        <tbody>
                            @foreach($cmByMaterial as $m)
                                <tr>
                                    <td>{{ $m['name'] }} @if($m['sku'])<small class="text-muted font-monospace">{{ $m['sku'] }}</small>@endif</td>
                                    <td class="text-end fw-bold">{{ $cmFmt($m['qty']) }} {{ $m['unit'] }}</td>
                                    <td class="text-end">{{ number_format($m['cost'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th>Total cost</th><th></th><th class="text-end">{{ number_format($cmByMaterial->sum('cost'), 2) }}</th></tr></tfoot>
                    </table>
                </div>
            </div>

            <div class="fw-bold fs-12 text-uppercase text-muted mb-2">Every logged line</div>
            <div class="table-responsive">
                <table class="erp-thin-table">
                    <thead>
                        <tr>
                            <th>Logged at</th><th>Operation</th><th>Production batch</th><th class="text-end">Output of log</th>
                            <th>Material</th><th class="text-end">Quantity</th><th>Warehouse</th><th>Inventory lot(s)</th><th class="text-end">Cost</th><th>Logged by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cmRows as $r)
                            <tr>
                                <td class="text-muted">{{ $r['recorded_at'] ? \Illuminate\Support\Carbon::parse($r['recorded_at'])->format('Y-m-d H:i') : '—' }} <small class="d-block font-monospace fs-10">Log #{{ $r['log_id'] }}</small></td>
                                <td>{{ trim(($r['operation_number'] ?? '') . ' ' . ($r['operation_name'] ?? '')) ?: '—' }}</td>
                                <td>{{ $r['production_batch'] ?? '—' }}</td>
                                <td class="text-end">{{ $cmFmt($r['quantity_produced']) }}</td>
                                <td>{{ $r['product_name'] }} @if($r['sku'])<small class="text-muted font-monospace d-block fs-10">{{ $r['sku'] }}</small>@endif</td>
                                <td class="text-end fw-bold">{{ $cmFmt($r['quantity']) }} {{ $r['unit'] }}</td>
                                <td class="text-muted">{{ $r['warehouse'] ?? '—' }}</td>
                                <td class="text-muted fs-11">{{ $r['inventory_lots'] ?: '—' }}</td>
                                <td class="text-end">{{ number_format($r['cost'], 2) }}</td>
                                <td>{{ $r['user'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
