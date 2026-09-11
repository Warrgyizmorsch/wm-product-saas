@extends('layouts.duralux')

@section('title', 'Audit Log | SaaS ERP')
@section('page-title', 'Access Audit Log')
@section('breadcrumb', 'Access / Audit Log')

@section('content')

    <x-ui.card class="border-0 shadow-sm mb-4">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div style="min-width: 280px;">
                <label class="odoo-form-label">Action</label>
                <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            @if (request('action'))
                <a href="{{ route('access.audit-log.index') }}" class="btn btn-light btn-sm">Clear filter</a>
            @endif
        </form>
    </x-ui.card>

    <x-ui.card title="Who Changed What" bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>Target User</th>
                    <th>Before</th>
                    <th>After</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="ps-4 fs-12">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="fs-12">{{ $log->actor?->name ?? '—' }}</td>
                        <td><x-ui.badge variant="info" soft>{{ $log->action }}</x-ui.badge></td>
                        <td class="fs-12">
                            @if ($log->subject_type)
                                {{ $log->subject_type }} #{{ $log->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="fs-12">{{ $log->targetUser?->name ?? '—' }}</td>
                        <td class="fs-11 text-muted">{{ $log->before ? json_encode($log->before) : '—' }}</td>
                        <td class="fs-11 text-muted">{{ $log->after ? json_encode($log->after) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No access-control changes logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endsection
