@extends('layouts.duralux')

@section('title', 'Edit Budget | SaaS ERP')
@section('page-title', 'Edit Budget')
@section('breadcrumb', 'Accounting / Budgets / Edit')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">Cannot save this budget</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('accounting.budgets.update', $budget) }}" method="POST" id="budgetForm">
            @csrf
            @method('PUT')
            @include('modules.accounting.budgets._form', ['submitLabel' => 'Update Budget'])
        </form>
    </div>
@endsection

@include('modules.accounting.budgets._form-scripts')
