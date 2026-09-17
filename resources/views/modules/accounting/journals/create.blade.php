@extends('layouts.duralux')

@section('title', 'New Journal | SaaS ERP')
@section('page-title', 'New Journal')
@section('breadcrumb', 'Accounting / Journals / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @include('modules.accounting.journals._form')
    </div>
@endsection
