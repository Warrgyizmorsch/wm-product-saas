@extends('layouts.duralux')

@section('title', 'Usage Limits | SaaS ERP')
@section('page-title', 'Usage Limits')
@section('breadcrumb', 'Platform / Usage Limits')

@section('content')

    <x-ui.card title="Consumption vs Plan Limits — All Tenants" bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">Tenant</th>
                    <th>Plan</th>
                    <th style="min-width: 220px;">Users</th>
                    <th>Limit Source</th>
                    <th class="text-end pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $percent = $row['user_percent'];
                        $barVariant = $percent === null ? 'primary' : ($percent >= 100 ? 'danger' : ($percent >= 80 ? 'warning' : 'success'));
                        $statusVariant = $percent === null ? 'secondary' : ($percent >= 100 ? 'danger' : ($percent >= 80 ? 'warning' : 'success'));
                        $statusLabel = $percent === null ? 'Unlimited' : ($percent >= 100 ? 'At Limit' : ($percent >= 80 ? 'Near Limit' : 'OK'));
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
                                    <div class="progress-bar bg-{{ $barVariant }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            @endif
                        </td>
                        <td><span class="fs-12 text-muted text-capitalize">{{ $row['source'] }}</span></td>
                        <td class="text-end pe-4">
                            <x-ui.badge variant="{{ $statusVariant }}" soft>{{ $statusLabel }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">No tenants yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <p class="text-muted fs-12 mt-3">
        Storage consumption is not tracked yet — this console currently reports seat usage only.
    </p>
@endsection
