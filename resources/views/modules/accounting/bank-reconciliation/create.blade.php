@extends('layouts.duralux')

@section('title', 'New Bank Reconciliation | SaaS ERP')
@section('page-title', 'New Bank Reconciliation')
@section('breadcrumb', 'Accounting / Bank Reconciliation / New')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.bank-reconciliation._form')
    </div>
@endsection
