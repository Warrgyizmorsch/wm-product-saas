@extends('layouts.duralux')

@section('title', 'New Revaluation | SaaS ERP')
@section('page-title', 'New Asset Revaluation')
@section('breadcrumb', 'Accounting / Fixed Assets / Revaluations / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.fixed-assets.revaluations._form')
    </div>
@endsection
