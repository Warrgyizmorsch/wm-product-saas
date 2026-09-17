@extends('layouts.duralux')

@section('title', 'New Write-off | SaaS ERP')
@section('page-title', 'New Asset Write-off')
@section('breadcrumb', 'Accounting / Fixed Assets / Write-offs / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.fixed-assets.write-offs._form')
    </div>
@endsection
