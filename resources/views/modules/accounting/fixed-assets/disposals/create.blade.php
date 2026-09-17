@extends('layouts.duralux')

@section('title', 'New Disposal | SaaS ERP')
@section('page-title', 'New Asset Disposal')
@section('breadcrumb', 'Accounting / Fixed Assets / Disposals / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.fixed-assets.disposals._form')
    </div>
@endsection
