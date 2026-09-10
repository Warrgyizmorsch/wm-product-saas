@extends('layouts.duralux')

@section('title', 'Budgets | SaaS ERP')
@section('page-title', 'Budgets')
@section('breadcrumb', 'Accounting / Budgets')

@section('content')
    <x-ui.card title="Budgets" bodyClass="p-0">
        <x-slot name="headerAction">
            <x-ui.button href="{{ route('accounting.budgets.create') }}" variant="primary" size="sm">
                <i class="feather-plus me-1"></i>New Budget
            </x-ui.button>
        </x-slot>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Name</th>
                    <th>Fiscal Year</th>
                    <th>Lines</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($budgets as $budget)
                    <tr>
                        <td class="ps-4 fw-bold">{{ $budget->name }}</td>
                        <td>{{ $budget->fiscalYear?->name }}</td>
                        <td>{{ $budget->lines()->count() }}</td>
                        <td>
                            @if ($budget->status === 'draft')
                                <x-ui.badge variant="secondary" soft>Draft</x-ui.badge>
                            @elseif ($budget->status === 'approved')
                                <x-ui.badge variant="success" soft>Approved</x-ui.badge>
                            @else
                                <x-ui.badge variant="info" soft>Locked</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            @if ($budget->isEditable())
                                <x-ui.icon-btn href="{{ route('accounting.budgets.edit', $budget) }}" variant="soft-primary" icon="feather-edit" title="Edit" />

                                @can('approve', $budget)
                                    <form action="{{ route('accounting.budgets.approve', $budget) }}" method="POST" class="d-inline">
                                        @csrf
                                        <x-ui.icon-btn type="submit" variant="soft-success" icon="feather-check" title="Approve" />
                                    </form>
                                @endcan

                                <form action="{{ route('accounting.budgets.destroy', $budget) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                            data-confirm-title="Delete Budget"
                                            data-confirm-message="Delete budget '{{ $budget->name }}'?" />
                                </form>
                            @else
                                <a href="{{ route('accounting.reports.budget-vs-actual', ['budget_id' => $budget->id]) }}" class="btn btn-xs btn-soft-secondary">View vs Actual</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="feather-info me-2"></i>No budgets created yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.confirm-modal />
@endsection
