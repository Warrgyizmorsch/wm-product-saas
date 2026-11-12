@extends('layouts.duralux')

@section('title', __('production.create_bom') . ' | SaaS ERP')
@section('page-title', __('production.create_bom'))
@section('breadcrumb', __('production.create_bom'))

@section('content')
    @include('modules.production.bom.partials._form', [
        'mode'                 => 'create',
        'bom'                  => null,
        'copyFromBom'          => $copyFromBom ?? null,
        'copyMode'             => $copyMode ?? null,
        'suggestedVersion'     => $suggestedVersion ?? null,
        'selectedProductId'    => $selectedProductId ?? null,
        'selectedRoutingId'    => $selectedRoutingId ?? '',
        'copyItems'            => $copyItems ?? null,
        'products'             => $products,
        'materials'            => $materials,
        'uoms'                 => $uoms,
        'routings'             => $routings,
        'routingAdvisoriesMap' => $routingAdvisoriesMap ?? [],
    ])
@endsection
