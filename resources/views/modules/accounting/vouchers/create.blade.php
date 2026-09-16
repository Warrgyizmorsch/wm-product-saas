@extends('layouts.duralux')

@section('title', 'New ' . $label . ' | SaaS ERP')
@section('page-title', 'New ' . $label)
@section('breadcrumb', 'Accounting / ' . $label . 's / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.vouchers._form')
    </div>
@endsection
