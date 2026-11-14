@extends('layouts.admin')

@section('title', 'Audit Log | Platform Console')
@section('page-title', 'Audit Log')

@section('content')
    <x-ui.card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fs-12">Tenant</label>
                <select name="tenant_id" class="form-select">
                    <option value="">All tenants</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fs-12">Action</label>
                <input type="text" name="action" value="{{ request('action') }}" class="form-control" placeholder="e.g. tenants.status">
            </div>
            <div class="col-md-2">
                <label class="form-label fs-12">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label fs-12">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card bodyClass="p-0">
        <x-ui.table>
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr>
                    <th class="ps-4">When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Tenant</th>
                    <th>Result</th>
                    <th class="pe-4">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="ps-4 fs-12 text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td>
                            <span class="d-block">{{ $log->user?->name ?? '—' }}</span>
                            <span class="fs-11 text-muted">{{ $log->user?->email }}</span>
                        </td>
                        <td>
                            <code class="fs-12">{{ $log->method }} {{ $log->action }}</code>
                            @if ($log->payload)
                                <details class="fs-11 mt-1">
                                    <summary class="text-muted">Details</summary>
                                    <pre class="mb-0 small">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td>{{ $log->tenant?->name ?? '—' }}</td>
                        <td>
                            <x-ui.badge :variant="$log->status_code < 400 ? 'success' : 'danger'" soft>{{ $log->status_code }}</x-ui.badge>
                        </td>
                        <td class="pe-4 fs-12 text-muted">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No console activity recorded.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <div class="mt-3">{{ $logs->links() }}</div>
@endsection
