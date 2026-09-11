@extends('layouts.duralux')

@php
    $groups = collect($matrix)->groupBy(fn ($row) => $row['permission']->module);
    $states = [
        \App\Domains\Access\Services\PermissionOverrideService::STATE_INHERIT => ['label' => 'Inherit', 'class' => 'text-muted'],
        \App\Domains\Access\Services\PermissionOverrideService::STATE_ALLOW => ['label' => 'Always Allow', 'class' => 'text-success'],
        \App\Domains\Access\Services\PermissionOverrideService::STATE_DENY => ['label' => 'Always Deny', 'class' => 'text-danger'],
    ];
@endphp

@section('title', 'Permission Overrides | '.$targetUser->name.' | SaaS ERP')
@section('page-title', 'Permission Overrides')
@section('breadcrumb', 'Access / Users / '.$targetUser->name.' / Overrides')

@section('page-actions')
    <a href="{{ route('access.users.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Users
    </a>
@endsection

@section('content')

    <x-ui.card class="border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div>
                <span class="text-muted fs-11 text-uppercase d-block">User</span>
                <span class="fw-semibold">{{ $targetUser->name }}</span>
            </div>
            <div>
                <span class="text-muted fs-11 text-uppercase d-block">Email</span>
                <span class="fw-semibold">{{ $targetUser->email }}</span>
            </div>
            <div class="flex-grow-1">
                <span class="text-muted fs-11 text-uppercase d-block">What this is</span>
                <span class="fs-12">
                    Overrides apply on top of everything this user's role(s) already grant. "Always Allow" grants a
                    permission even if no role does; "Always Deny" blocks it even if a role would otherwise allow it —
                    deny always wins. Leave a permission on "Inherit" to use the role grants only.
                </span>
            </div>
        </div>
    </x-ui.card>

    <form action="{{ route('access.users.overrides.update', $targetUser) }}" method="POST">
        @csrf
        @method('PUT')

        <x-ui.card title="Permission Overrides" bodyClass="p-0">
            <x-ui.table>
                @foreach ($groups as $module => $rows)
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">{{ $module }}</th>
                            <th style="width: 340px;">State</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $permission = $row['permission']; @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="d-block fw-semibold">{{ $permission->entity }}.{{ $permission->action }}</span>
                                    @if ($permission->description)
                                        <span class="fs-11 text-muted">{{ $permission->description }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-3">
                                        @foreach ($states as $value => $meta)
                                            <label class="d-flex align-items-center gap-1 mb-0 {{ $meta['class'] }} fs-12">
                                                <input
                                                    type="radio"
                                                    class="form-check-input mt-0"
                                                    name="overrides[{{ $permission->id }}][state]"
                                                    value="{{ $value }}"
                                                    @checked($row['state'] === $value)
                                                >
                                                {{ $meta['label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        class="form-control form-control-sm"
                                        name="overrides[{{ $permission->id }}][reason]"
                                        value="{{ old("overrides.{$permission->id}.reason", $row['reason']) }}"
                                        placeholder="Optional — why this override exists"
                                        maxlength="500"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <div class="d-flex justify-content-end mt-3">
            <x-ui.button type="submit" variant="primary" icon="feather-check-circle">Save Overrides</x-ui.button>
        </div>
    </form>
@endsection
