@php
    $editingId = old('account_id');
    $editingAccount = $editingId ? collect($tree)->pluck('account')->firstWhere('id', (int) $editingId) : null;
    $modalFormAction = $editingId ? route('accounting.chart-of-accounts.update', $editingId) : route('accounting.chart-of-accounts.store');
    $modalFormMethod = $editingId ? 'PUT' : 'POST';
@endphp

@extends('layouts.duralux')

@section('title', 'Chart of Accounts | SaaS ERP')
@section('page-title', 'Chart of Accounts')
@section('breadcrumb', 'Accounting / Chart of Accounts')

@section('page-actions')
    @can('create', \App\Domains\Accounting\Models\ChartOfAccount::class)
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#chartOfAccountModal">
            New Account
        </x-ui.button>
    @endcan
@endsection

@section('content')
    @if (session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            <strong>Cannot delete this account.</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-xxl-3 col-md-6">
            <x-ui.card>
                <span class="text-muted fs-12 text-uppercase">Total Accounts</span>
                <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['total'] }}</h3>
            </x-ui.card>
        </div>
        <div class="col-xxl-3 col-md-6">
            <x-ui.card>
                <span class="text-muted fs-12 text-uppercase">Active Accounts</span>
                <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['active'] }}</h3>
            </x-ui.card>
        </div>
        @foreach (['asset' => 'Assets', 'liability' => 'Liabilities', 'income' => 'Income'] as $type => $label)
            <div class="col-xxl-2 col-md-4">
                <x-ui.card>
                    <span class="text-muted fs-12 text-uppercase">{{ $label }}</span>
                    <h3 class="mb-0 mt-2 fw-bold text-dark">{{ $summary['by_type'][$type] ?? 0 }}</h3>
                </x-ui.card>
            </div>
        @endforeach
    </div>

    <x-ui.card title="Accounts" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Code / Name</th>
                    <th>Type</th>
                    <th>Normal Balance</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($tree as $row)
                    @php $account = $row['account']; @endphp
                    <tr>
                        <td class="ps-4" style="padding-left: {{ 24 + $row['depth'] * 22 }}px !important;">
                            <span class="fw-bold">{{ $account->code }}</span>
                            <span class="text-muted">{{ $account->name }}</span>
                        </td>
                        <td class="text-capitalize">{{ $account->type }}</td>
                        <td class="text-capitalize">{{ $account->normal_balance }}</td>
                        <td>
                            @if ($account->is_active)
                                <x-ui.badge variant="success" soft>Active</x-ui.badge>
                            @else
                                <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                            @endif
                            @if ($account->is_system)
                                <x-ui.badge variant="secondary" soft>System</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn type="button" class="edit-coa-btn" variant="soft-primary" icon="feather-edit-2" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#chartOfAccountModal"
                                    data-id="{{ $account->id }}"
                                    data-code="{{ $account->code }}"
                                    data-name="{{ $account->name }}"
                                    data-type="{{ $account->type }}"
                                    data-normal-balance="{{ $account->normal_balance }}"
                                    data-parent-id="{{ $account->parent_id }}"
                                    data-description="{{ $account->description }}"
                                    data-is-active="{{ $account->is_active ? '1' : '0' }}"
                                    data-is-system="{{ $account->is_system ? '1' : '0' }}" />
                            @can('delete', $account)
                                <form action="{{ route('accounting.chart-of-accounts.destroy', $account) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.icon-btn type="button" variant="soft-danger" icon="feather-trash-2" title="Delete"
                                            data-confirm-title="Delete Account"
                                            data-confirm-message="Delete account {{ $account->code }} {{ $account->name }}? This cannot be undone." />
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="feather-book fs-1 mb-2 d-block"></i>
                            No accounts found in this tenant workspace.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.modal id="chartOfAccountModal" :title="$editingId ? 'Edit Account' : 'New Account'"
                :formAction="$modalFormAction" :formMethod="$modalFormMethod"
                :submitText="$editingId ? 'Save Changes' : 'Create'">
        <input type="hidden" name="account_id" id="coaAccountId" value="{{ $editingId }}">

        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" class="mb-3">
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div id="coaSystemNotice" style="display: none;">
            <x-ui.alert variant="warning" icon="feather-lock" class="fs-12 mb-3">
                This is a system account and cannot be deleted, though its details can still be edited.
            </x-ui.alert>
        </div>

        <div class="row">
            <div class="col-md-6">
                <x-ui.input label="Code" name="code" id="coaCode" :value="old('code', $editingAccount?->code)" required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Name" name="name" id="coaName" :value="old('name', $editingAccount?->name)" required="true" />
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <x-ui.select label="Type" name="type" id="coaType" required="true"
                             :options="collect(\App\Domains\Accounting\Models\ChartOfAccount::TYPES)->mapWithKeys(fn ($t) => [$t => ucfirst($t)])->all()"
                             :selected="old('type', $editingAccount?->type)" />
            </div>
            <div class="col-md-6">
                <x-ui.select label="Normal Balance" name="normal_balance" id="coaNormalBalance" required="true"
                             :options="['debit' => 'Debit', 'credit' => 'Credit']"
                             :selected="old('normal_balance', $editingAccount?->normal_balance)" />
            </div>
        </div>

        <x-ui.select label="Parent Account" name="parent_id" id="coaParentId"
                     :options="['' => 'None (top level)'] + collect($parentOptions)->mapWithKeys(fn ($o) => [$o['account']->id => $o['label']])->all()"
                     :selected="old('parent_id', $editingAccount?->parent_id)" />

        <x-ui.textarea label="Description" name="description" id="coaDescription" rows="2" :value="old('description', $editingAccount?->description)" />

        <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-8">
                <input type="hidden" name="is_active" value="0">
                <x-ui.checkbox label="Active" name="is_active" id="coaIsActive" :checked="old('is_active', $editingAccount?->is_active ?? true)" />
            </div>
        </div>
    </x-ui.modal>

    <x-ui.confirm-modal />
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            const coaModal = document.getElementById('chartOfAccountModal');

            coaModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button || !button.classList.contains('edit-coa-btn')) {
                    return;
                }

                const data = button.dataset;

                coaModal.querySelector('.modal-title').textContent = 'Edit Account';
                $(coaModal).find('form').attr('action', `/accounting/chart-of-accounts/${data.id}`);
                if (!$(coaModal).find('input[name="_method"]').length) {
                    $(coaModal).find('form').prepend('<input type="hidden" name="_method" value="PUT">');
                }
                $(coaModal).find('button[type="submit"]').text('Save Changes');

                $('#coaAccountId').val(data.id);
                $('#coaCode').val(data.code);
                $('#coaName').val(data.name);
                $('#coaType').val(data.type);
                $('#coaNormalBalance').val(data.normalBalance);
                $('#coaParentId').val(data.parentId || '');
                $('#coaDescription').val(data.description);
                $('#coaIsActive').prop('checked', data.isActive === '1');
                $('#coaSystemNotice').toggle(data.isSystem === '1');
            });

            coaModal.addEventListener('hidden.bs.modal', function () {
                coaModal.querySelector('.modal-title').textContent = 'New Account';
                $(coaModal).find('form').attr('action', '{{ route('accounting.chart-of-accounts.store') }}');
                $(coaModal).find('input[name="_method"]').remove();
                $(coaModal).find('button[type="submit"]').text('Create');

                $(coaModal).find('form')[0].reset();
                $('#coaAccountId').val('');
                $('#coaSystemNotice').hide();
            });

            @if ($errors->any())
                new bootstrap.Modal(coaModal).show();
            @endif
        });
    </script>
@endpush

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush
