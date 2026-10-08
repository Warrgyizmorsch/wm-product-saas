@extends('layouts.duralux')

@section('title', 'Enterprise Lot Traceability | SaaS ERP')
@section('page-title', 'Enterprise Lot Traceability')
@section('breadcrumb', 'Lot Traceability')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">

        {{-- Search Sheet --}}
        <x-ui.odoo-form-ui type="sheet">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-search text-primary me-2"></i> Trace Batch / Serial / Order / Material Lot
                </h5>
                <span class="badge bg-soft-primary text-primary fs-12 px-2 py-1">
                    <i class="feather-git-commit me-1"></i> Full Bi-Directional Lineage
                </span>
            </div>

            <form method="GET" action="{{ route('production.mes.traceability.search') }}" id="traceabilityForm">
                <div class="row g-3 align-items-end fs-13 text-dark">
                    {{-- 1. Entity Type --}}
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-semibold text-dark fs-12 mb-1">Entity Type <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm fs-13 py-2" name="type" id="entityTypeSelect" onchange="handleEntityTypeChange(this.value)" required>
                            <option value="order" @selected(request('type', 'order') === 'order')>{{ __('production.production_order') }}</option>
                            <option value="batch" @selected(request('type') === 'batch')>Production Batch</option>
                            <option value="serial" @selected(request('type') === 'serial')>{{ __('production.serial_number') }}</option>
                            <option value="lot" @selected(request('type') === 'lot')>Inventory Lot / Raw Material</option>
                        </select>
                    </div>

                    {{-- 2. Trace Direction --}}
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-semibold text-dark fs-12 mb-1">Trace Direction</label>
                        <select class="form-select form-select-sm fs-13 py-2" name="direction" id="traceDirection">
                            <option value="both" @selected(request('direction', 'both') === 'both')>Full Genealogy (Both)</option>
                            <option value="backward" @selected(request('direction') === 'backward')>Backward Trace (Source Origin & Materials)</option>
                            <option value="forward" @selected(request('direction') === 'forward')>Forward Trace (Where Used & Dispatched)</option>
                        </select>
                    </div>

                    {{-- 3. Dynamic Entity Dropdown & Code Input --}}
                    <div class="col-lg-4 col-md-8">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-dark fs-12 mb-0" id="entitySelectorLabel">
                                Select Entity <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-link p-0 text-decoration-none fs-11 text-primary" id="toggleInputModeBtn" onclick="toggleInputMode()">
                                <i class="feather-edit-2 me-1"></i>Type manually
                            </button>
                        </div>

                        {{-- Dropdown selector mode --}}
                        <div id="dropdownModeContainer">
                            {{-- Dropdown for Production Orders --}}
                            <select class="form-select form-select-sm fs-13 py-2 entity-dropdown" id="orderSelect" onchange="syncCodeValue(this.value)">
                                <option value="">-- Select Production Order --</option>
                                @foreach($orders ?? [] as $o)
                                    <option value="{{ $o->order_number }}" @selected(request('type', 'order') === 'order' && request('code') === $o->order_number)>
                                        {{ $o->order_number }} &mdash; {{ $o->product?->name ?? 'Order #' . $o->id }} ({{ ucfirst($o->status) }})
                                    </option>
                                @endforeach
                            </select>

                            {{-- Dropdown for Production Batches --}}
                            <select class="form-select form-select-sm fs-13 py-2 entity-dropdown d-none" id="batchSelect" onchange="syncCodeValue(this.value)">
                                <option value="">-- Select Production Batch --</option>
                                @foreach($batches ?? [] as $b)
                                    <option value="{{ $b->batch_number }}" @selected(request('type') === 'batch' && request('code') === $b->batch_number)>
                                        {{ $b->batch_number }} &mdash; {{ $b->product?->name ?? 'Batch #' . $b->id }} ({{ ucfirst($b->status) }})
                                    </option>
                                @endforeach
                            </select>

                            {{-- Dropdown for Serial Numbers --}}
                            <select class="form-select form-select-sm fs-13 py-2 entity-dropdown d-none" id="serialSelect" onchange="syncCodeValue(this.value)">
                                <option value="">-- Select Serial Number --</option>
                                @foreach($serials ?? [] as $s)
                                    <option value="{{ $s->serial_number }}" @selected(request('type') === 'serial' && request('code') === $s->serial_number)>
                                        {{ $s->serial_number }} &mdash; {{ $s->product?->name ?? 'Serial #' . $s->id }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Dropdown for Inventory Lots --}}
                            <select class="form-select form-select-sm fs-13 py-2 entity-dropdown d-none" id="lotSelect" onchange="syncCodeValue(this.value)">
                                <option value="">-- Select Inventory Lot --</option>
                                @foreach($lots ?? [] as $l)
                                    <option value="{{ $l->batch_number }}" @selected(request('type') === 'lot' && request('code') === $l->batch_number)>
                                        {{ $l->batch_number }} &mdash; {{ $l->product?->name ?? 'Lot #' . $l->id }} (Qty: {{ number_format($l->quantity, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Manual text input mode --}}
                        <div id="manualModeContainer" class="d-none">
                            <input type="text" class="form-control form-control-sm fs-13 py-2" id="manualCodeInput" placeholder="Enter batch/serial/order number..." value="{{ request('code') }}" oninput="syncCodeValue(this.value)">
                        </div>

                        {{-- Hidden input holding the actual code submitted to controller --}}
                        <input type="hidden" name="code" id="actualCodeInput" value="{{ request('code') }}" required>
                    </div>

                    {{-- 4. Submit Button --}}
                    <div class="col-lg-2 col-md-4">
                        <button type="submit" class="btn btn-primary w-100 py-2 fs-13 fw-semibold">
                            <i class="feather-sliders me-1"></i> Trace
                        </button>
                    </div>
                </div>
            </form>
        </x-ui.odoo-form-ui>

        {{-- Trace Genealogy Output --}}
        @if(isset($nodes))
            @php
                $rootNode = collect($nodes)->firstWhere('depth', 0) ?? ($nodes[0] ?? null);
                $materialNodes = collect($nodes)->filter(fn($n) => in_array($n['type'], ['material', 'lot']));
                $orderNodes = collect($nodes)->filter(fn($n) => $n['type'] === 'order');
                $batchNodes = collect($nodes)->filter(fn($n) => $n['type'] === 'batch');
                $serialNodes = collect($nodes)->filter(fn($n) => $n['type'] === 'serial');
                $receiptNodes = collect($nodes)->filter(fn($n) => $n['type'] === 'receipt');
                $salesNodes = collect($nodes)->filter(fn($n) => $n['type'] === 'sales_order');
                $orderOperations = $rootNode['operations'] ?? [];
            @endphp

            <div class="mt-4">
                {{-- Trace Result Header Card --}}
                <div class="card border border-light shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-primary px-2 py-1 fs-11 text-uppercase">{{ $searchedType }}</span>
                                    <span class="badge bg-soft-info text-info fs-11 text-uppercase">
                                        <i class="feather-compass me-1"></i>{{ $searchedDirection ?? 'both' }}
                                    </span>
                                    @if(isset($rootNode['status']))
                                        <span class="badge bg-soft-success text-success fs-11 text-uppercase">
                                            {{ strtoupper($rootNode['status']) }}
                                        </span>
                                    @endif
                                </div>
                                <h4 class="fw-bold text-dark mb-1">{{ $rootNode['label'] ?? $searchedCode }}</h4>
                                <p class="text-muted fs-13 mb-0">{{ $rootNode['detail'] ?? '' }}</p>
                            </div>
                            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                    <a href="{{ route('production.mes.traceability.export-csv', ['type' => $searchedType, 'code' => $searchedCode]) }}" class="btn btn-outline-secondary btn-sm fs-12">
                                        <i class="feather-download me-1"></i>{{ __('production.export_csv') }}
                                    </a>
                                    <button type="button" class="btn btn-outline-primary btn-sm fs-12" onclick="window.print()">
                                        <i class="feather-printer me-1"></i>Print Report
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Lineage Metrics Strip --}}
                        <div class="row g-3 mt-3 pt-3 border-top">
                            <div class="col-6 col-md-3">
                                <div class="bg-light p-3 rounded border text-center">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Materials Consumed</div>
                                    <div class="fs-18 fw-bold text-success">{{ $materialNodes->count() }}</div>
                                    <div class="fs-11 text-muted">Raw Lots &amp; Issues</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light p-3 rounded border text-center">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">{{ __('production.total_operations') }}</div>
                                    <div class="fs-18 fw-bold text-primary">{{ count($orderOperations) }}</div>
                                    <div class="fs-11 text-muted">Shop Floor Steps</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light p-3 rounded border text-center">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Output Units / Batches</div>
                                    <div class="fs-18 fw-bold text-info">{{ $batchNodes->count() + $serialNodes->count() + $receiptNodes->count() }}</div>
                                    <div class="fs-11 text-muted">Finished Lots / Serials</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light p-3 rounded border text-center">
                                    <div class="text-muted fs-11 text-uppercase fw-semibold mb-1">Genealogy Connections</div>
                                    <div class="fs-18 fw-bold text-dark">{{ count($edges) }}</div>
                                    <div class="fs-11 text-muted">Active Trace Links</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Trace Details Tabs --}}
                <ul class="nav nav-tabs nav-tabs-custom mb-4" id="traceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold fs-13" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timelinePane" type="button" role="tab">
                            <i class="feather-git-merge me-1"></i> Visual Genealogy Flow
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materialsPane" type="button" role="tab">
                            <i class="feather-box me-1"></i> Raw Material Inputs ({{ $materialNodes->count() }})
                        </button>
                    </li>
                    @if(count($orderOperations) > 0)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold fs-13" id="operations-tab" data-bs-toggle="tab" data-bs-target="#operationsPane" type="button" role="tab">
                                <i class="feather-cpu me-1"></i> Routing Operations ({{ count($orderOperations) }})
                            </button>
                        </li>
                    @endif
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold fs-13" id="outputs-tab" data-bs-toggle="tab" data-bs-target="#outputsPane" type="button" role="tab">
                            <i class="feather-layers me-1"></i> Downstream Outputs &amp; Distribution
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="traceTabsContent">
                    {{-- TAB 1: Visual Genealogy Tree & Flow --}}
                    <div class="tab-pane fade show active" id="timelinePane" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="position-relative ps-4 border-start border-2 border-primary" style="margin-left: 20px;">
                                    @php
                                        $sortedNodes = collect($nodes)->sortBy('depth');
                                    @endphp

                                    @foreach($sortedNodes as $node)
                                        @php
                                            $nodeType = $node['type'] ?? 'unknown';
                                            $badgeClass = match($nodeType) {
                                                'order'       => 'bg-primary text-white',
                                                'batch'       => 'bg-info text-white',
                                                'serial'      => 'bg-purple text-white',
                                                'lot'         => 'bg-success text-white',
                                                'material'    => 'bg-success text-white',
                                                'receipt'     => 'bg-teal text-white',
                                                'sales_order' => 'bg-warning text-dark',
                                                default       => 'bg-secondary text-white',
                                            };
                                            $icon = match($nodeType) {
                                                'order'       => 'feather-cpu',
                                                'batch'       => 'feather-layers',
                                                'serial'      => 'feather-hash',
                                                'lot'         => 'feather-box',
                                                'material'    => 'feather-package',
                                                'receipt'     => 'feather-check-circle',
                                                'sales_order' => 'feather-shopping-bag',
                                                default       => 'feather-circle',
                                            };
                                        @endphp
                                        <div class="mb-4 position-relative">
                                            <div class="position-absolute start-0 translate-middle-x bg-primary rounded-circle border border-white shadow-sm" style="width: 16px; height: 16px; left: -29px; top: 14px;"></div>

                                            <div class="card border shadow-sm {{ $node['key'] === ($rootNode['key'] ?? '') ? 'border-primary bg-soft-primary' : '' }}">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="badge {{ $badgeClass }} text-uppercase font-monospace fs-11 px-2 py-1">
                                                            <i class="{{ $icon }} me-1"></i>{{ $nodeType }}
                                                        </span>
                                                        <span class="badge bg-light text-muted border fs-12">
                                                            <i class="feather-clock me-1"></i>{{ $node['date'] ?? '—' }}
                                                        </span>
                                                    </div>

                                                    <h6 class="fw-bold text-dark mb-1">{{ $node['label'] }}</h6>
                                                    <p class="text-muted fs-13 mb-2">{{ $node['detail'] }}</p>

                                                    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                                        @if(isset($node['status']))
                                                            <span class="badge bg-soft-success text-success font-monospace fs-11">
                                                                STATUS: {{ strtoupper($node['status']) }}
                                                            </span>
                                                        @endif
                                                        @if(isset($node['customer']))
                                                            <span class="badge bg-soft-warning text-dark fs-11">
                                                                <i class="feather-user me-1"></i>{{ $node['customer'] }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    {{-- Relationships / Derived Connections --}}
                                                    @php
                                                        $nodeEdges = collect($edges)->where('target_key', $node['key']);
                                                    @endphp
                                                    @if($nodeEdges->isNotEmpty())
                                                        <div class="mt-3 pt-2 border-top text-muted fs-11">
                                                            <div class="fw-semibold text-dark mb-1">
                                                                <i class="feather-corner-down-right text-primary me-1"></i> Connected Inputs:
                                                            </div>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($nodeEdges as $edge)
                                                                    @php
                                                                        $sourceNode = collect($nodes)->firstWhere('key', $edge['source_key']);
                                                                    @endphp
                                                                    <span class="badge bg-white text-dark font-monospace border px-2 py-1 shadow-sm">
                                                                        {{ $sourceNode['label'] ?? $edge['source_key'] }}
                                                                        <strong class="text-primary">(Qty: {{ number_format($edge['quantity'], 2) }})</strong>
                                                                        @if(!empty($edge['remarks']))
                                                                            <span class="text-muted">&bull; {{ $edge['remarks'] }}</span>
                                                                        @endif
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: Raw Material Inputs --}}
                    <div class="tab-pane fade" id="materialsPane" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-box text-success me-2"></i> Materials Consumed in Production
                                </h6>
                                @if($materialNodes->isNotEmpty())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle fs-13 mb-0">
                                            <thead class="bg-light text-muted uppercase fs-11">
                                                <tr>
                                                    <th>Material / Item</th>
                                                    <th>Type</th>
                                                    <th>Issued Date</th>
                                                    <th>Details / Warehouse</th>
                                                    <th class="text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($materialNodes as $mat)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold text-dark">{{ $mat['label'] }}</div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-soft-success text-success text-uppercase fs-11">{{ $mat['type'] }}</span>
                                                        </td>
                                                        <td>{{ $mat['date'] ?? '—' }}</td>
                                                        <td class="text-muted">{{ $mat['detail'] ?? '—' }}</td>
                                                        <td class="text-end">
                                                            <span class="badge bg-soft-info text-info">{{ strtoupper($mat['status'] ?? 'consumed') }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted fs-13">
                                        <i class="feather-info me-1"></i> No individual raw material records linked to this entity.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- TAB 3: Routing Operations (if available) --}}
                    @if(count($orderOperations) > 0)
                        <div class="tab-pane fade" id="operationsPane" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-dark mb-3">
                                        <i class="feather-cpu text-primary me-2"></i> MES Shop Floor Operations Executed
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle fs-13 mb-0">
                                            <thead class="bg-light text-muted uppercase fs-11">
                                                <tr>
                                                    <th style="width: 80px;">Seq</th>
                                                    <th>Operation Name</th>
                                                    <th>Work Center</th>
                                                    <th>Machine</th>
                                                    <th class="text-center">Produced</th>
                                                    <th class="text-center">Rejected</th>
                                                    <th class="text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($orderOperations as $op)
                                                    <tr>
                                                        <td class="fw-bold font-monospace text-muted">#{{ $op['sequence'] }}</td>
                                                        <td class="fw-semibold text-dark">{{ $op['name'] }}</td>
                                                        <td>{{ $op['work_center'] }}</td>
                                                        <td>{{ $op['machine'] }}</td>
                                                        <td class="text-center fw-bold text-success">{{ number_format($op['produced'], 2) }}</td>
                                                        <td class="text-center {{ $op['rejected'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                                            {{ number_format($op['rejected'], 2) }}
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="badge bg-soft-success text-success text-uppercase fs-11">
                                                                {{ $op['status'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- TAB 4: Outputs & Distribution --}}
                    <div class="tab-pane fade" id="outputsPane" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="feather-layers text-info me-2"></i> Finished Lots, Serials &amp; Customer Demand
                                </h6>
                                @php
                                    $outputItems = collect($nodes)->filter(fn($n) => in_array($n['type'], ['batch', 'serial', 'receipt', 'sales_order']));
                                @endphp
                                @if($outputItems->isNotEmpty())
                                    <div class="row g-3">
                                        @foreach($outputItems as $item)
                                            <div class="col-md-6">
                                                <div class="card border shadow-sm p-3 h-100">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="badge bg-soft-primary text-primary text-uppercase fs-11">{{ $item['type'] }}</span>
                                                        <span class="text-muted fs-12">{{ $item['date'] }}</span>
                                                    </div>
                                                    <h6 class="fw-bold text-dark mb-1">{{ $item['label'] }}</h6>
                                                    <p class="text-muted fs-13 mb-2">{{ $item['detail'] }}</p>
                                                    @if(isset($item['status']))
                                                        <span class="badge bg-soft-success text-success text-uppercase align-self-start fs-11">
                                                            {{ $item['status'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted fs-13">
                                        <i class="feather-info me-1"></i> No external output batches or sales orders directly downstream of this entity.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Script for handling Entity Type dropdown switching --}}
    <script>
        let isManualMode = false;

        function handleEntityTypeChange(type) {
            // Hide all entity dropdowns
            document.querySelectorAll('.entity-dropdown').forEach(el => el.classList.add('d-none'));

            // Show matching dropdown
            let activeDropdown = document.getElementById(type + 'Select');
            if (activeDropdown) {
                activeDropdown.classList.remove('d-none');
                // Sync code with selected value of newly visible dropdown
                syncCodeValue(activeDropdown.value);
            }
        }

        function syncCodeValue(value) {
            document.getElementById('actualCodeInput').value = value;
            document.getElementById('manualCodeInput').value = value;
        }

        function toggleInputMode() {
            isManualMode = !isManualMode;
            let dropdownContainer = document.getElementById('dropdownModeContainer');
            let manualContainer = document.getElementById('manualModeContainer');
            let toggleBtn = document.getElementById('toggleInputModeBtn');

            if (isManualMode) {
                dropdownContainer.classList.add('d-none');
                manualContainer.classList.remove('d-none');
                toggleBtn.innerHTML = '<i class="feather-list me-1"></i>Select from list';
                document.getElementById('manualCodeInput').focus();
            } else {
                manualContainer.classList.add('d-none');
                dropdownContainer.classList.remove('d-none');
                toggleBtn.innerHTML = '<i class="feather-edit-2 me-1"></i>Type manually';
                let currentType = document.getElementById('entityTypeSelect').value;
                let activeDropdown = document.getElementById(currentType + 'Select');
                if (activeDropdown && activeDropdown.value) {
                    syncCodeValue(activeDropdown.value);
                }
            }
        }

        // Initialize state on page load
        document.addEventListener('DOMContentLoaded', function () {
            let initialType = document.getElementById('entityTypeSelect').value;
            handleEntityTypeChange(initialType);

            // If a code was already submitted/present in request, sync it
            let currentCode = document.getElementById('actualCodeInput').value;
            if (currentCode) {
                let currentSelect = document.getElementById(initialType + 'Select');
                if (currentSelect) {
                    currentSelect.value = currentCode;
                }
                document.getElementById('manualCodeInput').value = currentCode;
            } else {
                let currentSelect = document.getElementById(initialType + 'Select');
                if (currentSelect && currentSelect.value) {
                    syncCodeValue(currentSelect.value);
                }
            }
        });
    </script>
@endsection
