@extends('layouts.duralux')

@section('title', 'New Budget | SaaS ERP')
@section('page-title', 'New Budget')
@section('breadcrumb', 'Accounting / Budgets / Create')

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

        <form action="{{ route('accounting.budgets.store') }}" method="POST" id="budgetForm">
            @csrf
            @include('modules.accounting.budgets._form', ['submitLabel' => 'Create Budget'])
        </form>
    </div>
@endsection

@include('modules.accounting.budgets._form-scripts')
