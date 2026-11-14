@extends('layouts.admin')

@section('title', 'Usage Limits | Platform Console')
@section('page-title', 'Usage Limits')
@section('breadcrumb', 'Usage Limits')

@section('content')

    <x-ui.card title="Consumption vs Plan Limits — All Tenants" bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">Tenant</th>
                    <th>Plan</th>
                    <th style="min-width: 200px;">Users</th>
                    <th style="min-width: 160px;">Storage</th>
                    <th style="min-width: 360px;">Resources</th>
                    <th>Limit Source</th>
                    <th class="text-end pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $barVariant = fn ($p) => $p === null ? 'primary' : ($p >= 100 ? 'danger' : ($p >= 80 ? 'warning' : 'success'));
                        $percent = $row['user_percent'];
                        // Status reflects the tightest cap, users or any resource.
                        $worst = $row['worst_percent'];
                        $statusVariant = $worst === null ? 'secondary' : ($worst >= 100 ? 'danger' : ($worst >= 80 ? 'warning' : 'success'));
                        $statusLabel = $worst === null ? 'Unlimited' : ($worst >= 100 ? 'At Limit' : ($worst >= 80 ? 'Near Limit' : 'OK'));
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar-text avatar-md bg-soft-primary text-primary">{{ substr($row['tenant']->name, 0, 1) }}</span>
                                <div>
                                    <span class="d-block fw-semibold text-dark">{{ $row['tenant']->name }}</span>
                                    <span class="fs-11 text-muted">{{ $row['tenant']->slug }}</span>
                                </div>
                            </div>
                        </td>
                        <td><x-ui.badge variant="info" soft>{{ $row['plan_name'] }}</x-ui.badge></td>
                        <td>
                            <div class="d-flex justify-content-between fs-12 mb-1">
                                <span>{{ $row['used_users'] }} / {{ $row['max_users'] ?? '∞' }}</span>
                                @if ($row['max_users'] !== null)
                                    <span class="text-muted">{{ $row['remaining_users'] }} left</span>
                                @endif
                            </div>
                            @if ($percent !== null)
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $barVariant($percent) }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            @php $storage = $row['storage']; @endphp
                            <div class="fs-12 mb-1">
                                {{ \App\Domains\Platform\Services\TenantStorage::format($storage['used']) }}
                                / {{ $storage['limit'] === null ? '∞' : \App\Domains\Platform\Services\TenantStorage::format($storage['limit']) }}
                            </div>
                            @if ($storage['percent'] !== null)
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $barVariant($storage['percent']) }}" role="progressbar" style="width: {{ $storage['percent'] }}%;" aria-label="Storage usage" aria-valuenow="{{ $storage['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="row g-2">
                                @foreach ($row['resources'] as $resource)
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between fs-11 mb-1">
                                            <span class="text-muted">{{ $resource['label'] }}</span>
                                            <span class="{{ ($resource['percent'] ?? 0) >= 100 ? 'text-danger fw-semibold' : '' }}">{{ number_format($resource['used']) }} / {{ $resource['limit'] === null ? '∞' : number_format($resource['limit']) }}</span>
                                        </div>
                                        @if ($resource['percent'] !== null)
                                            <div class="progress" style="height: 4px;">
                                                <div class="progress-bar bg-{{ $barVariant($resource['percent']) }}" role="progressbar" style="width: {{ $resource['percent'] }}%;" aria-label="{{ $resource['label'] }} usage" aria-valuenow="{{ $resource['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </td>
                        <td><span class="fs-12 text-muted text-capitalize">{{ $row['source'] }}</span></td>
                        <td class="text-end pe-4">
                            <x-ui.badge variant="{{ $statusVariant }}" soft>{{ $statusLabel }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No tenants yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <p class="text-muted fs-12 mt-3">
        Resource caps come from each tenant's plan (Plans → Edit → Usage Limits); ∞ = no cap. "Limit Source" applies to users and storage. Storage counts uploaded files and is recounted nightly (tenant:storage-sync).
    </p>
@endsection
