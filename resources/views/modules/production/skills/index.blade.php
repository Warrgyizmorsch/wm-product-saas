@extends('layouts.duralux')

@section('title', 'Operator Skills & Qualifications | SaaS ERP')
@section('page-title', 'Operator Skills Matrix')
@section('breadcrumb', 'Operator Skills')

@section('page-actions')
    <a href="{{ route('production.operator-skills.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Map New Skill
    </a>
@endsection

@section('content')
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel">
        <!-- Toolbar: Search, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Shop Floor Qualifications Matrix</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.operator-skills.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search by operator or skill..." value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.operator-skills.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Skills Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 25%">Operator / User</th>
                    <th style="width: 20%">Skill Code / qualification</th>
                    <th style="width: 20%">Work Center Limit</th>
                    <th style="width: 20%">Machine Limit</th>
                    <th style="width: 10%">Status</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($skills as $sk)
                    <tr>
                        <td class="fw-bold text-dark">
                            <a href="{{ route('production.operator-skills.edit', $sk->id) }}" class="text-dark hover-primary">
                                {{ $sk->user->name ?? 'User #'.$sk->user_id }}
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-soft-primary text-primary font-monospace">{{ $sk->skill_code }}</span>
                        </td>
                        <td>
                            @if($sk->workCenter)
                                <span class="fw-medium text-dark"><i class="feather-settings me-1 text-muted"></i>{{ $sk->workCenter->name }}</span>
                            @else
                                <span class="text-muted">Unrestricted (All)</span>
                            @endif
                        </td>
                        <td>
                            @if($sk->machine)
                                <span class="fw-medium text-dark"><i class="feather-cpu me-1 text-muted"></i>{{ $sk->machine->name }}</span>
                            @else
                                <span class="text-muted">Unrestricted (All)</span>
                            @endif
                        </td>
                        <td>
                            @if($sk->active)
                                <span class="erp-badge-active">Active</span>
                            @else
                                <span class="erp-badge-draft">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('production.operator-skills.edit', $sk->id) }}" class="btn btn-xs btn-outline-primary py-1"><i class="feather-edit-3 me-1"></i>Edit</a>
                                <form method="POST" action="{{ route('production.operator-skills.destroy', $sk->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this skill mapping?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger py-1"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="feather-info me-2 fs-16"></i>No operator skill qualifications registered.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $skills->links() }}
        </div>
    </div>
@endsection
