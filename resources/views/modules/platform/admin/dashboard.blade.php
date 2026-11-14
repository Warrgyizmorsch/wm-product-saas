@extends('layouts.admin')

@section('title', 'Platform Console | '.config('app.name'))
@section('page-title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <x-ui.kpi-card title="Total tenants" :value="number_format($summary['total'])" icon="feather-briefcase" tone="brand"
                :href="$canManageTenants ? route('platform.tenants.index') : null" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.kpi-card title="Active" :value="number_format($summary['active'])" icon="feather-check-circle" tone="positive" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.kpi-card title="On trial" :value="number_format($summary['trial'])" icon="feather-clock" tone="pending" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.kpi-card title="Suspended" :value="number_format($summary['suspended'])" icon="feather-pause-circle" tone="negative" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.card title="Needs attention" bodyClass="p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="feather-phone-call me-2 text-primary"></i>New demo requests</span>
                        @if ($canManageLeads)
                            <a href="{{ route('platform.demo-requests.index', ['status' => 'new']) }}" class="fw-semibold">{{ $newDemoRequests }}</a>
                        @else
                            <span class="fw-semibold">{{ $newDemoRequests }}</span>
                        @endif
                    </li>
                    @forelse ($trialEndingSoon as $tenant)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="feather-clock me-2 text-warning"></i>{{ $tenant->name }} <span class="text-muted fs-12">trial ends</span></span>
                            <span class="fs-12">{{ $tenant->trial_ends_at->format('d M Y') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted fs-12">No trials ending in the next 7 days.</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
        <div class="col-lg-7">
            <x-ui.card title="Recent console activity" bodyClass="p-0">
                <x-ui.table>
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th class="pe-4">Tenant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentActivity as $log)
                            <tr>
                                <td class="ps-4 fs-12 text-muted">{{ $log->created_at->diffForHumans() }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td><code class="fs-12">{{ $log->action }}</code></td>
                                <td class="pe-4">{{ $log->tenant?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
@endsection
