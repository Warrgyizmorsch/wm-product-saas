@if(request()->routeIs('production.*') || request()->is('production*'))
@php

    $steps = [
        [
            'id' => 'work-centers',
            'num' => '1',
            'name' => 'Work Centers',
            'route' => 'production.work-centers.index',
            'active_routes' => ['production.work-centers.*', 'production.machines.*', 'production.shifts.*'],
            'icon' => 'feather-cpu',
        ],
        [
            'id' => 'routings',
            'num' => '2',
            'name' => 'Routings',
            'route' => 'production.routing.index',
            'active_routes' => ['production.routing.*'],
            'icon' => 'feather-sliders',
        ],
        [
            'id' => 'boms',
            'num' => '3',
            'name' => 'BOMs',
            'route' => 'production.boms.index',
            'active_routes' => ['production.boms.*'],
            'icon' => 'feather-layers',
        ],
        [
            'id' => 'orders',
            'num' => '4',
            'name' => 'Production Orders',
            'route' => 'production.orders.index',
            'active_routes' => ['production.orders.*', 'production.cost-adjustments.*', 'sales.material-requests.*'],
            'icon' => 'feather-file-text',
        ],
        [
            'id' => 'schedules',
            'num' => '5',
            'name' => 'Schedules',
            'route' => 'production.schedules.index',
            'active_routes' => ['production.schedules.*', 'production.capacity.*', 'production.calendars.*'],
            'icon' => 'feather-calendar',
        ],
        [
            'id' => 'mes',
            'num' => '6',
            'name' => 'Shop Floor (MES)',
            'route' => 'production.mes.dashboard',
            'active_routes' => ['production.mes.*', 'production.scanners.*'],
            'icon' => 'feather-monitor',
        ],
        [
            'id' => 'wip',
            'num' => '7',
            'name' => 'WIP Tracking',
            'route' => 'production.wip.index',
            'active_routes' => ['production.wip.*'],
            'icon' => 'feather-activity',
        ],
        [
            'id' => 'quality',
            'num' => '8',
            'name' => 'Quality Control',
            'route' => 'production.quality.dashboard',
            'active_routes' => ['production.quality.*', 'production.scrap.*', 'production.rework.*', 'production.ncrs.*', 'production.capas.*', 'production.inspections.*', 'production.quality-plans.*'],
            'icon' => 'feather-shield',
        ],
    ];

    // Determine active step index
    $activeStepIndex = -1;
    foreach ($steps as $index => $step) {
        foreach ($step['active_routes'] as $pattern) {
            if (request()->routeIs($pattern)) {
                $activeStepIndex = $index;
                break 2;
            }
        }
    }
@endphp

<div class="production-workflow-card bg-white border rounded shadow-sm p-1 mb-0">
    <!-- Row 1: Header Badge & Flow Control Buttons -->
    <div class="d-flex align-items-center justify-content-between pb-1 mb-2 border-bottom flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary text-white px-2.5 py-1.5 fs-11 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                <i class="feather-git-commit me-1"></i> MFG FLOW
            </span>
            <span class="text-muted fs-12 fw-medium d-none d-md-inline-block">
                Work Centers <i class="feather-arrow-right fs-10 mx-1"></i> Routings <i class="feather-arrow-right fs-10 mx-1"></i> BOMs <i class="feather-arrow-right fs-10 mx-1"></i> Orders <i class="feather-arrow-right fs-10 mx-1"></i> Schedules <i class="feather-arrow-right fs-10 mx-1"></i> Shop Floor <i class="feather-arrow-right fs-10 mx-1"></i> WIP <i class="feather-arrow-right fs-10 mx-1"></i> Quality
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            @if($activeStepIndex > 0)
                @php $prevStep = $steps[$activeStepIndex - 1]; @endphp
                <a href="{{ Route::has($prevStep['route']) ? route($prevStep['route']) : '#' }}"
                   class="btn btn-sm btn-light border fw-semibold fs-12 px-3 py-1 text-dark d-inline-flex align-items-center gap-1 shadow-2subtle">
                    <i class="feather-arrow-left fs-12"></i>
                    <span>{{ $prevStep['name'] }}</span>
                </a>
            @endif

            @if($activeStepIndex >= 0 && $activeStepIndex < count($steps) - 1)
                @php $nextStep = $steps[$activeStepIndex + 1]; @endphp
                <a href="{{ Route::has($nextStep['route']) ? route($nextStep['route']) : '#' }}"
                   class="btn btn-sm btn-primary fw-semibold fs-12 px-3 py-1 d-inline-flex align-items-center gap-1 shadow-sm">
                    <span>Next: {{ $nextStep['name'] }}</span>
                    <i class="feather-arrow-right fs-12"></i>
                </a>
            @endif
        </div>
    </div>

    <!-- Row 2: Full-Width Clickable Flow Steps Pipeline Tabs -->
    <div class="production-flow-tabs-wrapper pb-1">
        <div class="production-flow-tabs-inner gap-1">
            @foreach($steps as $index => $step)
                @php
                    $isActive = ($index === $activeStepIndex);
                    $isPassed = ($activeStepIndex >= 0 && $index < $activeStepIndex);
                @endphp

                <a href="{{ Route::has($step['route']) ? route($step['route']) : '#' }}"
                   class="production-flow-tab-item d-inline-flex align-items-center gap-1 px-2 py-1 rounded-3 text-decoration-none transition-all flex-shrink-0 {{ $isActive ? 'active-step bg-primary text-white fw-bold shadow' : ($isPassed ? 'passed-step bg-soft-primary text-primary fw-semibold' : 'future-step bg-light text-dark border hover-primary') }}">
                    <span class="step-circle-badge d-inline-flex align-items-center justify-content-center rounded-circle fs-11 {{ $isActive ? 'bg-white text-primary fw-bold' : ($isPassed ? 'bg-primary text-white' : 'bg-secondary text-white') }}" style="width: 22px; height: 22px; flex-shrink: 0;">
                        {{ $step['num'] }}
                    </span>
                    <i class="{{ $step['icon'] }} fs-13"></i>
                    <span class="fs-12 whitespace-nowrap">{{ $step['name'] }}</span>
                </a>

                @if(!$loop->last)
                    <div class="step-connector-arrow d-flex align-items-center flex-shrink-0 px-0.5">
                        <i class="feather-chevron-right text-muted opacity-50 fs-12"></i>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endif
